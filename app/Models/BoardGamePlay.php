<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BoardGamePlay model representing a board game play record.
 *
 * This model represents a single play session of a board game, including
 * the date, location, players, expansions used, and optional sync information
 * with BoardGameGeek.
 */
class BoardGamePlay extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'board_game_id',
        'group_id',
        'created_by_user_id',
        'played_at',
        'location',
        'comment',
        'game_length_minutes',
        'source',
        'bgg_play_id',
        'bgg_synced_at',
        'bgg_sync_status',
        'bgg_sync_error_message',
        'bgg_synced_to_at',
        'bgg_sync_to_status',
        'bgg_sync_to_error_message',
        'sync_to_bgg',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'played_at' => 'date',
            'game_length_minutes' => 'integer',
            'bgg_synced_at' => 'datetime',
            'bgg_synced_to_at' => 'datetime',
            'sync_to_bgg' => 'boolean',
        ];
    }

    /**
     * Get the board game for this play.
     */
    public function boardGame(): BelongsTo
    {
        return $this->belongsTo(BoardGame::class);
    }

    /**
     * Get the group for this play.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the user who created this play.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the players for this play.
     */
    public function players(): HasMany
    {
        return $this->hasMany(BoardGamePlayPlayer::class);
    }

    /**
     * Get the expansions used in this play.
     */
    public function expansions(): BelongsToMany
    {
        return $this->belongsToMany(BoardGame::class, 'board_game_play_expansions')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include plays for a specific group.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Group $group
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForGroup($query, Group $group)
    {
        return $query->where('group_id', $group->id);
    }

    /**
     * Scope a query to only include plays created by a specific user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where('created_by_user_id', $user->id);
    }

    /**
     * Scope a query to only include plays for a specific board game.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param BoardGame $boardGame
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForBoardGame($query, BoardGame $boardGame)
    {
        return $query->where('board_game_id', $boardGame->id);
    }

    /**
     * Scope a query to only include plays from a specific source.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $source
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope a query to only include plays pending sync to BGG.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePendingBggSync($query)
    {
        return $query->where('sync_to_bgg', true)
            ->where(function ($q) {
                $q->whereNull('bgg_sync_to_status')
                    ->orWhere('bgg_sync_to_status', 'pending')
                    ->orWhere('bgg_sync_to_status', 'failed');
            });
    }

    /**
     * Check if this play is from BoardGameGeek.
     *
     * @return bool
     */
    public function isFromBoardGameGeek(): bool
    {
        return $this->source === 'boardgamegeek';
    }

    /**
     * Check if this play has been synced to BoardGameGeek.
     *
     * @return bool
     */
    public function isSyncedToBoardGameGeek(): bool
    {
        return $this->bgg_sync_to_status === 'synced' && $this->bgg_play_id !== null;
    }

    /**
     * Get the number of players in this play.
     *
     * @return int
     */
    public function getPlayerCount(): int
    {
        return $this->players()->count();
    }

    /**
     * Get the winning players for this play.
     *
     * @return Collection<int, BoardGamePlayPlayer>
     */
    public function getWinners(): Collection
    {
        return $this->players()->where('is_winner', true)->get();
    }
}
