<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BoardGamePlayPlayer model representing a player in a board game play.
 *
 * This model represents a single player's participation in a play session,
 * including their score, winner status, and whether they were a new player.
 * A player can be identified by user_id, board_game_geek_username, or guest_name.
 */
class BoardGamePlayPlayer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'board_game_play_id',
        'user_id',
        'board_game_geek_username',
        'guest_name',
        'score',
        'is_winner',
        'is_new_player',
        'position',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'is_winner' => 'boolean',
            'is_new_player' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Get the play this player belongs to.
     */
    public function boardGamePlay(): BelongsTo
    {
        return $this->belongsTo(BoardGamePlay::class);
    }

    /**
     * Get the user this player is linked to (if applicable).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the player identifier (display name).
     *
     * @return string
     */
    public function getPlayerIdentifier(): string
    {
        if ($this->user_id !== null && $this->user !== null) {
            return $this->user->name;
        }

        if ($this->board_game_geek_username !== null) {
            return $this->board_game_geek_username;
        }

        if ($this->guest_name !== null) {
            return $this->guest_name;
        }

        return 'Unknown Player';
    }

    /**
     * Check if this player is linked to a user.
     *
     * @return bool
     */
    public function isUserPlayer(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Check if this player is identified by BGG username.
     *
     * @return bool
     */
    public function isBggPlayer(): bool
    {
        return $this->board_game_geek_username !== null;
    }

    /**
     * Check if this player is a guest.
     *
     * @return bool
     */
    public function isGuestPlayer(): bool
    {
        return $this->guest_name !== null;
    }
}
