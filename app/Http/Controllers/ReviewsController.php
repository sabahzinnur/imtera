<?php

namespace App\Http\Controllers;

use App\Jobs\SyncYandexReviews;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewsController extends Controller
{
    /**
     * Display a listing of the reviews.
     */
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $setting = $user->yandexSetting;
        $sort = $request->query('sort', 'newest');
        $perPage = (int) $request->query('per_page', 50);

        if ($setting && $setting->business_id && ! $setting->isSyncing()) {
            $lastSynced = $setting->last_synced_at;
            if (! $lastSynced || $lastSynced->diffInMinutes(now()) >= 10) {
                SyncYandexReviews::dispatch($user->id);
            }
        }

        $reviews = Review::where('user_id', $user->id)
            ->sorted($sort)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Review $review) => [
                'id' => $review->id,
                'author_name' => $review->author_name,
                'author_phone' => $review->author_phone,
                'branch_name' => $review->branch_name,
                'rating' => $review->rating,
                'text' => $review->text,
                'published_at' => $review->published_at?->format('d.m.Y H:i'),
            ]);

        return Inertia::render('Reviews', [
            'reviews' => $reviews,
            'setting' => $setting,
            'sort' => $sort,
            'perPage' => $perPage,
            'isSyncing' => $setting?->isSyncing() ?? false,
        ]);
    }

    /**
     * Manually trigger a synchronization.
     */
    public function sync(): RedirectResponse
    {
        $user = auth()->user();
        $setting = $user->yandexSetting;

        if ($setting && $setting->business_id && ! $setting->isSyncing()) {
            SyncYandexReviews::dispatch($user->id);

            return back()->with('success', __('Синхронизация запущена.'));
        }

        return back();
    }
}
