<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use App\Models\YandexSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class YandexSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviews_page_receives_branch_name(): void
    {
        $user = User::factory()->create();

        $setting = YandexSetting::create([
            'user_id' => $user->id,
            'maps_url' => 'https://yandex.ru/maps/org/test/123/reviews/',
            'business_id' => '123',
            'business_name' => 'Main Office',
            'sync_status' => 'completed',
        ]);

        Review::create([
            'user_id' => $user->id,
            'yandex_review_id' => 'rev1',
            'author_name' => 'John Doe',
            'branch_name' => 'Branch 1',
            'rating' => 5,
            'text' => 'Great!',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/reviews');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reviews')
            ->has('reviews.data', 1)
            ->where('reviews.data.0.branch_name', 'Branch 1')
            ->where('setting.business_name', 'Main Office')
        );
    }
}
