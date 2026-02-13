<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'user_id',
        'yandex_review_id',
        'author_name',
        'author_phone',
        'branch_name',
        'rating',
        'text',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'rating' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to sort reviews.
     */
    public function scopeSorted($query, string $sort = 'newest')
    {
        return $query->when($sort === 'oldest',
            fn ($q) => $q->orderBy('published_at', 'asc'),
            fn ($q) => $q->orderBy('published_at', 'desc')
        );
    }
}
