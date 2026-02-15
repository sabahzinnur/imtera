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
        public ?string $reqId = null
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

        try {
            if ($this->page === 0) {
                $isInterrupted = $setting->sync_status !== 'completed' &&
                                 $setting->sync_status !== 'pending' &&
                                 $setting->sync_page > 0;

                if ($isInterrupted) {
                    $currentPage = $setting->sync_page;
                } else {
                    $currentPage = 0;
                    $setting->update([
                        'previous_sync_status' => $setting->sync_status,
                        'sync_page' => 0,
                    ]);

                    if ($setting->sync_status === 'completed') {
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

                $session = $parser->prepareSession();
                $sessionId = $session['sessionId'];
                $reqId = $session['reqId'];
            }

            $response = $parser->fetchPageWithRetry(
                $setting->business_id,
                $csrfToken,
                $currentPage,
                $sessionId,
                $reqId
            );

            $result = $response['data'];
            $newCsrfToken = $response['csrfToken'];
            $newRating = $result['rating'] ?: $response['rating'];
            $newVotes = $result['total'] ?: $response['votes'];
            $businessName = $response['businessName'];

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

            $nextPage = $currentPage + 1;
            $isFinished = $lastReviewReached ||
                         ($nextPage >= self::MAX_PAGES) ||
                         ($nextPage >= $result['totalPages']) ||
                         empty($result['reviews']);

            // Формируем данные для обновления настроек
            $updateData = [];
            if ($newRating > 0) $updateData['rating'] = $newRating;
            if ($newVotes > 0) $updateData['reviews_count'] = $newVotes;
            if ($businessName) $updateData['business_name'] = $businessName;

            if ($isFinished) {
                $updateData['sync_status'] = 'completed';
                $updateData['sync_page'] = 0;
                $updateData['last_synced_at'] = now();
            } else {
                $updateData['sync_page'] = $nextPage;
            }

            $setting->update($updateData);

            if (! $isFinished) {
                self::dispatch(
                    $this->userId,
                    $nextPage,
                    $lastReviewId,
                    $newCsrfToken,
                    $sessionId,
                    $reqId
                )->delay(now()->addMilliseconds(YandexSetting::SYNC_PAGE_DELAY_MS));
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
                'sync_page' => $currentPage,
                'sync_error' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }
}
