<?php

namespace App\Jobs;

use App\Models\Review;
use App\Models\YandexSetting;
use App\Services\YandexMapsParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncYandexReviews implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    private const MAX_PAGES = 12;

    public function __construct(
        public int $userId,
        public int $page = 0,
        public ?string $lastReviewId = null,
        public ?string $csrfToken = null,
        public ?string $sessionId = null,
        public ?string $reqId = null,
        public ?string $businessName = null
    ) {}

    public function handle(YandexMapsParser $parser): void
    {
        $setting = YandexSetting::where('user_id', $this->userId)
            ->whereNotNull('business_id')
            ->first();

        if (! $setting) {
            return;
        }

        $currentPage = $this->page;
        $lastReviewId = $this->lastReviewId;
        $csrfToken = $this->csrfToken;
        $sessionId = $this->sessionId;
        $reqId = $this->reqId;
        $businessName = $this->businessName;

        try {
            // Всегда получаем CSRF и устанавливаем куки для текущего запроса
            $csrfToken = $parser->fetchCsrfToken($setting->business_id);
            if (! $csrfToken) {
                throw new \RuntimeException('Не удалось получить CSRF-токен');
            }

            if ($this->page === 0) {
                $isInterrupted = $setting->sync_status !== 'completed' && 
                                 $setting->sync_status !== 'pending' && 
                                 $setting->sync_page > 0;

                if ($isInterrupted) {
                    // Возобновляем со страницы, на которой прервались
                    $currentPage = $setting->sync_page;
                } else {
                    // Новая синхронизация или обновление (с 1-й страницы)
                    $currentPage = 0;
                    $setting->update([
                        'previous_sync_status' => $setting->sync_status,
                        'sync_page' => 0,
                    ]);

                    if ($setting->sync_status === 'completed') {
                        // Это обновление: ищем до последнего известного отзыва
                        $lastReview = Review::where('user_id', $this->userId)
                            ->orderBy('published_at', 'desc')
                            ->first();
                        $lastReviewId = $lastReview?->yandex_review_id;
                    }
                }

                $setting->update([
                    'sync_status' => 'syncing',
                    'sync_error' => null,
                ]);

                $businessName = $parser->getExtractedBusinessName();
                $session = $parser->prepareSession();
                $sessionId = $session['sessionId'];
                $reqId = $session['reqId'];
            }

            $rawResult = $parser->fetchPage($setting->business_id, $csrfToken, $sessionId, $reqId, $currentPage);
            $result = $parser->mapSinglePage($rawResult);

            $lastReviewReached = false;

            DB::transaction(function () use ($result, &$lastReviewReached, $lastReviewId, $businessName, $setting) {
                foreach ($result['reviews'] as $data) {
                    if ($lastReviewId && $data['yandex_review_id'] === $lastReviewId) {
                        $lastReviewReached = true;
                        break;
                    }

                    Review::updateOrCreate(
                        [
                            'user_id' => $this->userId,
                            'yandex_review_id' => $data['yandex_review_id'],
                        ],
                        array_merge($data, [
                            'user_id' => $this->userId,
                            'branch_name' => $data['branch_name'] ?? $businessName ?? $setting->business_name
                        ])
                    );
                }
            });

            // Страница обработана успешно. Если это было прерывание, currentPage мог быть > 0.
            // Следующая страница для очереди — currentPage + 1.
            $nextPage = $currentPage + 1;

            $isFinished = $lastReviewReached ||
                         ($nextPage >= self::MAX_PAGES) ||
                         ($nextPage >= $result['totalPages']) ||
                         empty($result['reviews']);

            if ($isFinished) {
                $setting->update([
                    'rating' => $result['rating'] ?: $setting->rating,
                    'reviews_count' => $result['total'] ?: $setting->reviews_count,
                    'business_name' => $businessName ?: $setting->business_name,
                    'last_synced_at' => now(),
                    'sync_status' => 'completed',
                    'sync_page' => 0,
                ]);
            } else {
                $setting->update([
                    'sync_page' => $nextPage, // Сохраняем следующую страницу на случай прерывания
                    'rating' => ($currentPage === 0) ? ($result['rating'] ?: $setting->rating) : $setting->rating,
                    'reviews_count' => ($currentPage === 0) ? ($result['total'] ?: $setting->reviews_count) : $setting->reviews_count,
                    'business_name' => ($currentPage === 0) ? ($businessName ?: $setting->business_name) : $setting->business_name,
                ]);

                self::dispatch(
                    $this->userId,
                    $nextPage,
                    $lastReviewId,
                    null, // Токен получим заново в следующем джобе для установки кук
                    $sessionId,
                    $reqId,
                    $businessName
                )->delay(now()->addMilliseconds(100));
            }

        } catch (\Exception $e) {
            Log::error('SyncYandexReviews failed', [
                'user' => $this->userId,
                'page' => $currentPage,
                'error' => $e->getMessage(),
            ]);

            $statusToRevert = ($setting->previous_sync_status === 'completed') ? 'completed' : 'failed';

            $setting->update([
                'sync_status' => $statusToRevert,
                'sync_page' => $currentPage, // Оставляем текущую страницу для возобновления
                'sync_error' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }
}
