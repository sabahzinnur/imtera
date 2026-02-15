<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use App\Models\YandexSetting;
use App\Services\YandexMapsParser;
use App\Jobs\SyncYandexReviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;
use Mockery;

use Illuminate\Support\Facades\Queue;

class YandexNewAlgorithmTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    public function test_saving_settings_deletes_reviews_and_starts_sync(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        Review::create([
            'user_id' => $user->id,
            'yandex_review_id' => 'old',
            'author_name' => 'Old',
            'rating' => 5
        ]);

        $response = $this->actingAs($user)->post('/yandex-settings', [
            'maps_url' => 'https://yandex.ru/maps/org/test/1010501395/reviews/'
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);
        $this->assertEquals(0, Review::where('user_id', $user->id)->count());
        
        $setting = $user->yandexSetting;
        $this->assertEquals('pending', $setting->sync_status);
        $this->assertEquals(0, $setting->sync_page);
        Queue::assertPushed(SyncYandexReviews::class);
    }

    public function test_sync_reverts_status_if_update_fails(): void
    {
        $user = User::factory()->create();
        $setting = YandexSetting::create([
            'user_id' => $user->id,
            'maps_url' => 'https://yandex.ru/maps/org/test/123/reviews/',
            'business_id' => '123',
            'sync_status' => 'completed',
            'previous_sync_status' => 'completed',
            'sync_page' => 0
        ]);

        $parser = Mockery::mock(YandexMapsParser::class);
        $parser->shouldReceive('prepareSession')->andReturn(['sessionId' => 's', 'reqId' => 'r']);
        $parser->shouldReceive('fetchPageWithRetry')->andThrow(new \Exception('Yandex Error'));

        $job = new SyncYandexReviews($user->id);
        try {
            $job->handle($parser);
        } catch (\Exception $e) {
            // expected
        }

        $setting->refresh();
        // Should revert to completed because it was an update
        $this->assertEquals('completed', $setting->sync_status);
        $this->assertEquals('Yandex Error', $setting->sync_error);
    }

    public function test_sync_sets_failed_if_initial_fails(): void
    {
        $user = User::factory()->create();
        $setting = YandexSetting::create([
            'user_id' => $user->id,
            'maps_url' => 'https://yandex.ru/maps/org/test/123/reviews/',
            'business_id' => '123',
            'sync_status' => 'pending',
            'previous_sync_status' => null,
            'sync_page' => 0
        ]);

        $parser = Mockery::mock(YandexMapsParser::class);
        $parser->shouldReceive('prepareSession')->andReturn(['sessionId' => 's', 'reqId' => 'r']);
        $parser->shouldReceive('fetchPageWithRetry')->andThrow(new \Exception('Initial Error'));

        $job = new SyncYandexReviews($user->id);
        try {
            $job->handle($parser);
        } catch (\Exception $e) {
            // expected
        }

        $setting->refresh();
        // Should be failed because it was NOT an update
        $this->assertEquals('failed', $setting->sync_status);
        $this->assertEquals('Initial Error', $setting->sync_error);
    }

    public function test_sync_resumes_if_interrupted(): void
    {
        $user = User::factory()->create();
        $setting = YandexSetting::create([
            'user_id' => $user->id,
            'maps_url' => 'https://yandex.ru/maps/org/test/123/reviews/',
            'business_id' => '123',
            'sync_status' => 'failed',
            'sync_page' => 5
        ]);

        $parser = Mockery::mock(YandexMapsParser::class);
        $parser->shouldReceive('prepareSession')->andReturn(['sessionId' => 's', 'reqId' => 'r']);
        
        $parser->shouldReceive('fetchPageWithRetry')
            ->once()
            ->andReturn([
                'data' => [
                    'reviews' => [
                        ['yandex_review_id' => 'new', 'rating' => 5, 'author_name' => 'Tester']
                    ],
                    'rating' => 4.5,
                    'total' => 100,
                    'totalPages' => 6 // Page 5 is the last page (0-indexed)
                ],
                'csrfToken' => 'token',
                'rating' => 4.5,
                'votes' => 100,
                'businessName' => 'Name'
            ]);

        $job = new SyncYandexReviews($user->id);
        $job->handle($parser);

        $setting->refresh();
        $this->assertEquals('completed', $setting->sync_status);
        $this->assertEquals(0, $setting->sync_page);
    }

    public function test_sync_retries_on_empty_results_if_not_end(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $setting = YandexSetting::create([
            'user_id' => $user->id,
            'maps_url' => 'https://yandex.ru/maps/org/test/123/reviews/',
            'business_id' => '123',
            'sync_status' => 'pending',
            'sync_page' => 0
        ]);

        $parser = Mockery::mock(YandexMapsParser::class);
        $parser->shouldReceive('prepareSession')->andReturn(['sessionId' => 's', 'reqId' => 'r']);
        
        $parser->shouldReceive('fetchPageWithRetry')
            ->once()
            ->andReturn([
                'data' => [
                    'reviews' => [],
                    'rating' => 4.5,
                    'total' => 100,
                    'totalPages' => 2
                ],
                'csrfToken' => 'token',
                'rating' => 4.5,
                'votes' => 100,
                'businessName' => 'Name'
            ]);

        $job = new SyncYandexReviews($user->id);
        $job->handle($parser);

        Queue::assertPushed(SyncYandexReviews::class, function ($job) {
            return $job->page === 0 && $job->retryCount === 1 && $job->csrfToken === null;
        });
    }

    public function test_sync_fails_after_retry_if_still_empty(): void
    {
        $user = User::factory()->create();
        $setting = YandexSetting::create([
            'user_id' => $user->id,
            'maps_url' => 'https://yandex.ru/maps/org/test/123/reviews/',
            'business_id' => '123',
            'sync_status' => 'syncing',
            'sync_page' => 0,
            'total_pages' => 2
        ]);

        $parser = Mockery::mock(YandexMapsParser::class);
        $parser->shouldReceive('prepareSession')->andReturn(['sessionId' => 's', 'reqId' => 'r']);
        $parser->shouldReceive('fetchPageWithRetry')
            ->once()
            ->andReturn([
                'data' => [
                    'reviews' => [],
                    'rating' => 4.5,
                    'total' => 100,
                    'totalPages' => 2
                ],
                'csrfToken' => 'token',
                'rating' => 4.5,
                'votes' => 100,
                'businessName' => 'Name'
            ]);

        $job = new SyncYandexReviews($user->id, 0, null, 'token', 's', 'r', 1);
        try {
            $job->handle($parser);
        } catch (\Exception $e) {
            // expected
        }

        $setting->refresh();
        $this->assertEquals('failed', $setting->sync_status);
        $this->assertStringContainsString('Не удалось получить все отзывы', $setting->sync_error);
    }
}
