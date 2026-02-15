<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single item in a user's BGG collection (one row per user + BGG object, e.g. version).
 * bgg_id is the base/original game ID used to link to board_games.
 */
class BggCollectionItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'bgg_id',
        'bgg_object_id',
        'bgg_version_id',
        'bgg_collid',
        'board_game_id',
        'name',
        'year_published',
        'thumbnail_url',
        'image_url',
        'user_rating',
        'owned',
        'numplays',
        'prev_owned',
        'for_trade',
        'want',
        'want_to_play',
        'want_to_buy',
        'wishlist',
        'preordered',
        'last_synced_at',
        'bgg_last_modified',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_rating' => 'decimal:2',
            'owned' => 'boolean',
            'numplays' => 'integer',
            'prev_owned' => 'boolean',
            'for_trade' => 'boolean',
            'want' => 'boolean',
            'want_to_play' => 'boolean',
            'want_to_buy' => 'boolean',
            'wishlist' => 'boolean',
            'preordered' => 'boolean',
            'last_synced_at' => 'datetime',
            'bgg_last_modified' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function boardGame(): BelongsTo
    {
        return $this->belongsTo(BoardGame::class);
    }
}
