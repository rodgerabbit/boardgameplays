<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\BoardGamePlayPlayer;
use App\Models\User;

/**
 * Service class for managing board game play players.
 *
 * This service handles business logic related to players in plays, including
 * new player detection and player identifier resolution.
 */
class BoardGamePlayPlayerService extends BaseService
{
    /**
     * Check if this is the first play for a player.
     *
     * @param BoardGame $boardGame The board game
     * @param User|null $user The user (if linked)
     * @param string|null $bggUsername The BGG username (if applicable)
     * @param string|null $guestName The guest name (if applicable)
     * @param BoardGamePlay|null $excludePlay The play to exclude from the check (current play)
     * @return bool True if this is the first play
     */
    public function isFirstPlayForPlayer(
        BoardGame $boardGame,
        ?User $user = null,
        ?string $bggUsername = null,
        ?string $guestName = null,
        ?BoardGamePlay $excludePlay = null
    ): bool {
        $query = BoardGamePlay::query()
            ->where('board_game_id', $boardGame->id)
            ->notExcluded() // Exclude duplicate plays from first play detection
            ->whereHas('players', function ($q) use ($user, $bggUsername, $guestName) {
                if ($user !== null) {
                    $q->where('user_id', $user->id);
                } elseif ($bggUsername !== null) {
                    $q->where('board_game_geek_username', $bggUsername);
                } elseif ($guestName !== null) {
                    $q->where('guest_name', $guestName);
                }
            });

        // Exclude the current play if provided (for updates or when checking during creation)
        if ($excludePlay !== null) {
            $query->where('id', '!=', $excludePlay->id);
        }

        return $query->count() === 0;
    }

    /**
     * Resolve player identifier from provided data.
     *
     * @param int|null $userId The user ID
     * @param string|null $bggUsername The BGG username
     * @param string|null $guestName The guest name
     * @return array<string, mixed> The resolved identifier data
     */
    public function resolvePlayerIdentifier(?int $userId = null, ?string $bggUsername = null, ?string $guestName = null): array
    {
        $identifier = [
            'user_id' => null,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ];

        if ($userId !== null) {
            $identifier['user_id'] = $userId;
        } elseif ($bggUsername !== null) {
            $identifier['board_game_geek_username'] = $bggUsername;
        } elseif ($guestName !== null) {
            $identifier['guest_name'] = $guestName;
        }

        return $identifier;
    }
}

