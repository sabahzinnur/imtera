<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YandexSetting extends Model
{
    public const SYNC_AUTO_REFRESH_MINUTES = 10;

    public const SYNC_PAGE_DELAY_MS = 100;

    public const POLLING_INTERVAL_MS = 1000;

    protected $fillable = [
        'user_id',
        'maps_url',
        'business_id',
        'business_name',
        'rating',
        'reviews_count',
        'last_synced_at',
        'sync_status',
        'sync_page',
        'total_pages',
        'previous_sync_status',
        'sync_error',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'rating' => 'float',
        'reviews_count' => 'integer',
        'sync_page' => 'integer',
        'total_pages' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the reviews are currently being synced.
     */
    public function isSyncing(): bool
    {
        return in_array($this->sync_status, ['pending', 'syncing']);
    }

    /**
     * Check if the sync was aborted.
     */
    public function isAborted(): bool
    {
        return $this->sync_status === 'aborted';
    }
}
