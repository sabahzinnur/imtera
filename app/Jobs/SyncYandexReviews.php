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
use Illuminate\Support\Facades\Log;

class SyncYandexReviews implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $userId) {}

    public function handle(YandexMapsParser $parser): void
    {
        $setting = YandexSetting::where('user_id', $this->userId)
            ->whereNotNull('business_id')
            ->first();

        if (! $setting) {
            return;
        }

        try {
            $result = $parser->fetchAllReviews($setting->business_id);

            foreach ($result['reviews'] as $data) {
                Review::updateOrCreate(
                    ['user_id' => $this->userId, 'yandex_review_id' => $data['yandex_review_id']],
                    array_merge($data, ['user_id' => $this->userId])
                );
            }

            $setting->update([
                'rating' => $result['rating'],
                'reviews_count' => $result['total'],
                'last_synced_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('SyncYandexReviews failed', [
                'user' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            $this->fail($e);
        }
    }
}
