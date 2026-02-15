<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records a change to a user's BGG collection (added, removed, rating changed, etc.)
 * for display in activity/history.
 */
class BggCollectionItemChange extends Model
{
    public const CHANGE_TYPE_ADDED = 'added';
    public const CHANGE_TYPE_REMOVED = 'removed';
    public const CHANGE_TYPE_RATING_CHANGED = 'rating_changed';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'bgg_collection_sync_id',
        'change_type',
        'bgg_id',
        'bgg_object_id',
        'old_value',
        'new_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bggCollectionSync(): BelongsTo
    {
        return $this->belongsTo(BggCollectionSync::class, 'bgg_collection_sync_id');
    }
}
