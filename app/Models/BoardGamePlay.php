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
        'is_excluded',
        'leading_play_id',
        'excluded_at',
        'exclusion_reason',
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
            'is_excluded' => 'boolean',
            'excluded_at' => 'datetime',
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

    /**
     * Scope a query to only include excluded plays.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcluded($query)
    {
        return $query->where('is_excluded', true);
    }

    /**
     * Scope a query to only include non-excluded plays.
     *
     * This is the default scope for statistics queries to exclude duplicates.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotExcluded($query)
    {
        return $query->where('is_excluded', false);
    }

    /**
     * Scope a query to only include leading plays.
     *
     * Leading plays are plays that are not excluded and do not have a leading_play_id set.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLeading($query)
    {
        return $query->where('is_excluded', false)
            ->whereNull('leading_play_id');
    }

    /**
     * Check if this play is excluded.
     *
     * @return bool
     */
    public function isExcluded(): bool
    {
        return $this->is_excluded === true;
    }

    /**
     * Check if this play is a leading play.
     *
     * A leading play is not excluded and does not have a leading_play_id set.
     *
     * @return bool
     */
    public function isLeading(): bool
    {
        return !$this->is_excluded && $this->leading_play_id === null;
    }

    /**
     * Get the leading play if this play is excluded.
     *
     * @return BoardGamePlay|null
     */
    public function getLeadingPlay(): ?BoardGamePlay
    {
        if (!$this->is_excluded || $this->leading_play_id === null) {
            return null;
        }

        return self::find($this->leading_play_id);
    }

    /**
     * Get all excluded plays that point to this play as the leading play.
     *
     * @return Collection<int, BoardGamePlay>
     */
    public function getExcludedPlays(): Collection
    {
        return self::where('leading_play_id', $this->id)
            ->where('is_excluded', true)
            ->get();
    }

    /**
     * Get the relationship to the leading play.
     *
     * @return BelongsTo
     */
    public function leadingPlay(): BelongsTo
    {
        return $this->belongsTo(BoardGamePlay::class, 'leading_play_id');
    }
}
