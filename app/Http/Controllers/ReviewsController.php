<?php

namespace App\Http\Controllers;

use App\Models\Review;
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

        $reviews = Review::where('user_id', $user->id)
            ->sorted($sort)
            ->paginate(10)
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
            'isSyncing' => $setting?->isSyncing() ?? false,
        ]);
    }
}
