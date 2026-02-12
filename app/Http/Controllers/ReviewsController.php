<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\YandexSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewsController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = auth()->id();
        $setting = YandexSetting::where('user_id', $userId)->first();
        $sort = $request->get('sort', 'newest');

        $reviews = Review::where('user_id', $userId)
            ->when($sort === 'newest', fn ($q) => $q->orderBy('published_at', 'desc'))
            ->when($sort === 'oldest', fn ($q) => $q->orderBy('published_at', 'asc'))
            ->paginate(10)
            ->through(fn ($r) => [
                'id' => $r->id,
                'author_name' => $r->author_name,
                'author_phone' => $r->author_phone,
                'branch_name' => $r->branch_name,
                'rating' => $r->rating,
                'text' => $r->text,
                'published_at' => $r->published_at?->format('d.m.Y H:i'),
            ]);

        return Inertia::render('Reviews', [
            'reviews' => $reviews,
            'setting' => $setting,
            'sort' => $sort,
            'isSyncing' => $setting && in_array($setting->sync_status, ['pending', 'syncing']),
        ]);
    }
}
