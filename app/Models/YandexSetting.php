<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YandexSetting extends Model
{
    protected $fillable = [
        'user_id',
        'maps_url',
        'business_id',
        'rating',
        'reviews_count',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'rating' => 'float',
        'reviews_count' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
