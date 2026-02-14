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

    public int $timeout = 300; // Увеличим таймаут для парсинга нескольких страниц

    public function __construct(public int $userId) {}

    public function handle(YandexMapsParser $parser): void
    {
        $setting = YandexSetting::where('user_id', $this->userId)
            ->whereNotNull('business_id')
            ->first();

        if (! $setting) {
            return;
        }

        $currentCount = Review::where('user_id', $this->userId)->count();
        $isFirstSync = ! $setting->last_synced_at || $currentCount === 0;

        $setting->update([
            'sync_status' => 'syncing',
            'sync_error' => null,
        ]);

        try {
            if ($isFirstSync) {
                // ПЕРВАЯ СИНХРОНИЗАЦИЯ: удаляем старое и качаем всё
                Review::where('user_id', $this->userId)->delete();
                $result = $parser->fetchAllReviews($setting->business_id, startPage: 0);
            } elseif ($setting->sync_status === 'completed') {
                // ПОЛУЧАЕМ ТОЛЬКО НОВЫЕ: если прошлая синхронизация была успешной
                $lastReview = Review::where('user_id', $this->userId)
                    ->orderBy('published_at', 'desc')
                    ->first();

                // Сначала проверяем первую страницу
                $firstPage = $parser->fetchAllReviews(
                    $setting->business_id,
                    maxPages: 1,
                    lastReviewId: $lastReview?->yandex_review_id
                );

                // Если количество не изменилось (требование 4) и есть старые отзывы, то завершаем
                if ($setting->reviews_count > 0 && $setting->reviews_count === $firstPage['total']) {
                    $setting->update([
                        'last_synced_at' => now(),
                        'sync_status' => 'completed',
                    ]);

                    return;
                }

                // Иначе качаем новые до последнего известного
                $result = $parser->fetchAllReviews(
                    $setting->business_id,
                    lastReviewId: $lastReview?->yandex_review_id
                );
            } else {
                // ПРОДОЛЖАЕМ: если прошлая была прервана или неудачна
                $startPage = (int) floor($currentCount / 50);
                $result = $parser->fetchAllReviews($setting->business_id, startPage: $startPage);
            }

            DB::transaction(function () use ($result) {
                foreach ($result['reviews'] as $data) {
                    Review::updateOrCreate(
                        [
                            'user_id' => $this->userId,
                            'yandex_review_id' => $data['yandex_review_id'],
                        ],
                        array_merge($data, ['user_id' => $this->userId])
                    );
                }
            });

            $setting->update([
                'rating' => $result['rating'],
                'reviews_count' => $result['total'],
                'last_synced_at' => now(),
                'sync_status' => $result['is_aborted'] ? 'aborted' : 'completed',
            ]);

        } catch (\Exception $e) {
            Log::error('SyncYandexReviews failed', [
                'user' => $this->userId,
                'error' => $e->getMessage(),
            ]);

            $setting->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }
}
