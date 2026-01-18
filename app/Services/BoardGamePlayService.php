<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SyncBoardGamePlayToBoardGameGeekJob;
use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\BoardGamePlayPlayer;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Service class for managing board game plays.
 *
 * This service handles all business logic related to board game plays, including
 * creation, updates, deletion, player management, and BGG sync queuing.
 */
class BoardGamePlayService extends BaseService
{
    /**
     * Create a new instance of the service.
     */
    public function __construct(
        private readonly BoardGamePlayDeduplicationService $deduplicationService
    ) {
    }

    /**
     * Create a new board game play with validation.
     *
     * @param array<string, mixed> $playData The play data
     * @param User $user The user creating the play
     * @return BoardGamePlay The created play
     */
    public function createBoardGamePlay(array $playData, User $user): BoardGamePlay
    {
        return DB::transaction(function () use ($playData, $user): BoardGamePlay {
            // Validate board game is not an expansion
            $boardGame = BoardGame::findOrFail($playData['board_game_id']);
            $this->validateBoardGameIsNotExpansion($boardGame);

            // Validate expansions if provided
            if (isset($playData['expansions']) && is_array($playData['expansions'])) {
                foreach ($playData['expansions'] as $expansionId) {
                    $expansion = BoardGame::findOrFail($expansionId);
                    $this->validateExpansionIsExpansion($expansion);
                }
            }

            // Validate player count
            if (isset($playData['players']) && is_array($playData['players'])) {
                $this->validatePlayerCount(count($playData['players']));
            }

            // Set default group if not provided
            if (!isset($playData['group_id'])) {
                $defaultGroup = $this->getDefaultGroupForUser($user);
                if ($defaultGroup !== null) {
                    $playData['group_id'] = $defaultGroup->id;
                }
            }

            // Extract players, expansions, and BGG sync data from play data
            $players = $playData['players'] ?? [];
            $expansions = $playData['expansions'] ?? [];
            $syncToBgg = $playData['sync_to_bgg'] ?? false;
            $bggUsername = $playData['board_game_geek_username'] ?? null;
            $bggPassword = $playData['board_game_geek_password'] ?? null;
            unset($playData['players'], $playData['expansions'], $playData['sync_to_bgg'], $playData['board_game_geek_username'], $playData['board_game_geek_password']);

            // Set source and creator
            $playData['source'] = $playData['source'] ?? 'website';
            $playData['created_by_user_id'] = $user->id;

            // Create the play
            $play = BoardGamePlay::create($playData);

            // Create players
            foreach ($players as $playerData) {
                BoardGamePlayPlayer::create([
                    'board_game_play_id' => $play->id,
                    'user_id' => $playerData['user_id'] ?? null,
                    'board_game_geek_username' => $playerData['board_game_geek_username'] ?? null,
                    'guest_name' => $playerData['guest_name'] ?? null,
                    'score' => $playerData['score'] ?? null,
                    'is_winner' => $playerData['is_winner'] ?? false,
                    'position' => $playerData['position'] ?? null,
                ]);
            }

            // Attach expansions if provided
            if (!empty($expansions)) {
                $play->expansions()->attach($expansions);
            }

            // Detect new players
            $play->refresh();
            $this->detectNewPlayers($play);

            // Queue BGG sync if requested
            if ($syncToBgg) {
                $this->queueBggSyncIfRequested($play->fresh(), $bggUsername, $bggPassword);
            }

            // Sync deduplication after play is created
            $play->refresh();
            $this->deduplicationService->syncDeduplicationForPlay($play);

            return $play->fresh(['boardGame', 'group', 'creator', 'players', 'expansions']);
        });
    }

    /**
     * Update a board game play.
     *
     * @param BoardGamePlay $play The play to update
     * @param array<string, mixed> $playData The updated play data
     * @return BoardGamePlay The updated play
     */
    public function updateBoardGamePlay(BoardGamePlay $play, array $playData): BoardGamePlay
    {
        return DB::transaction(function () use ($play, $playData): BoardGamePlay {
            // Validate board game if changed
            if (isset($playData['board_game_id'])) {
                $boardGame = BoardGame::findOrFail($playData['board_game_id']);
                $this->validateBoardGameIsNotExpansion($boardGame);
            }

            // Validate expansions if provided
            if (isset($playData['expansions']) && is_array($playData['expansions'])) {
                foreach ($playData['expansions'] as $expansionId) {
                    $expansion = BoardGame::findOrFail($expansionId);
                    $this->validateExpansionIsExpansion($expansion);
                }
            }

            // Extract players and expansions from play data
            $players = $playData['players'] ?? null;
            $expansions = $playData['expansions'] ?? null;
            unset($playData['players'], $playData['expansions']);

            // Update the play
            $play->update($playData);

            // Update players if provided
            if ($players !== null) {
                $this->validatePlayerCount(count($players));

                // Delete existing players
                $play->players()->delete();

                // Create new players
                foreach ($players as $playerData) {
                    BoardGamePlayPlayer::create([
                        'board_game_play_id' => $play->id,
                        'user_id' => $playerData['user_id'] ?? null,
                        'board_game_geek_username' => $playerData['board_game_geek_username'] ?? null,
                        'guest_name' => $playerData['guest_name'] ?? null,
                        'score' => $playerData['score'] ?? null,
                        'is_winner' => $playerData['is_winner'] ?? false,
                        'position' => $playerData['position'] ?? null,
                    ]);
                }

                // Re-detect new players
                $play->refresh();
                $this->detectNewPlayers($play);
            }

            // Update expansions if provided
            if ($expansions !== null) {
                $play->expansions()->sync($expansions);
            }

            // Sync deduplication after play is updated (may affect other plays)
            $play->refresh();
            $this->deduplicationService->syncDeduplicationForPlay($play);

            return $play->fresh(['boardGame', 'group', 'creator', 'players', 'expansions']);
        });
    }

    /**
     * Delete a board game play.
     *
     * If the play is a leading play, promote another from the excluded group.
     * If the play is excluded, just delete it.
     *
     * @param BoardGamePlay $play The play to delete
     * @return bool True if deleted successfully
     */
    public function deleteBoardGamePlay(BoardGamePlay $play): bool
    {
        return DB::transaction(function () use ($play): bool {
            // If this is a leading play, we need to promote another from the excluded group
            if ($play->isLeading()) {
                $excludedPlays = $play->getExcludedPlays();

                if ($excludedPlays->isNotEmpty()) {
                    // Promote the first excluded play to be the new leading play
                    $newLeadingPlay = $excludedPlays->first();
                    $this->deduplicationService->clearExclusion($newLeadingPlay);

                    // Update other excluded plays to point to the new leading play
                    foreach ($excludedPlays->where('id', '!=', $newLeadingPlay->id) as $excludedPlay) {
                        $excludedPlay->update(['leading_play_id' => $newLeadingPlay->id]);
                    }

                    // Re-sync deduplication for the new leading play to ensure consistency
                    $newLeadingPlay->refresh();
                    $this->deduplicationService->syncDeduplicationForPlay($newLeadingPlay);
                }
            }

            // Delete the play
            return $play->delete();
        });
    }

    /**
     * Add a player to a play.
     *
     * @param BoardGamePlay $play The play
     * @param array<string, mixed> $playerData The player data
     * @return BoardGamePlayPlayer The created player
     */
    public function addPlayerToPlay(BoardGamePlay $play, array $playerData): BoardGamePlayPlayer
    {
        $this->validatePlayerCount($play->getPlayerCount() + 1);

        return BoardGamePlayPlayer::create([
            'board_game_play_id' => $play->id,
            'user_id' => $playerData['user_id'] ?? null,
            'board_game_geek_username' => $playerData['board_game_geek_username'] ?? null,
            'guest_name' => $playerData['guest_name'] ?? null,
            'score' => $playerData['score'] ?? null,
            'is_winner' => $playerData['is_winner'] ?? false,
            'position' => $playerData['position'] ?? null,
        ]);
    }

    /**
     * Remove a player from a play.
     *
     * @param BoardGamePlay $play The play
     * @param int $playerId The player ID to remove
     * @return bool True if removed successfully
     */
    public function removePlayerFromPlay(BoardGamePlay $play, int $playerId): bool
    {
        return BoardGamePlayPlayer::where('board_game_play_id', $play->id)
            ->where('id', $playerId)
            ->delete();
    }

    /**
     * Auto-detect and mark new players.
     *
     * @param BoardGamePlay $play The play
     * @return void
     */
    public function detectNewPlayers(BoardGamePlay $play): void
    {
        $playerService = new BoardGamePlayPlayerService();

        foreach ($play->players as $player) {
            $isFirstPlay = $playerService->isFirstPlayForPlayer(
                $play->boardGame,
                $player->user,
                $player->board_game_geek_username,
                $player->guest_name,
                $play // Exclude current play from check
            );

            $player->update(['is_new_player' => $isFirstPlay]);
        }
    }

    /**
     * Validate that a board game is not an expansion.
     *
     * @param BoardGame $boardGame The board game to validate
     * @return void
     * @throws \InvalidArgumentException If the board game is an expansion
     */
    public function validateBoardGameIsNotExpansion(BoardGame $boardGame): void
    {
        if ($boardGame->is_expansion) {
            throw new \InvalidArgumentException('Board game must not be an expansion. Use a base game instead.');
        }
    }

    /**
     * Validate that a board game is an expansion.
     *
     * @param BoardGame $boardGame The board game to validate
     * @return void
     * @throws \InvalidArgumentException If the board game is not an expansion
     */
    public function validateExpansionIsExpansion(BoardGame $boardGame): void
    {
        if (!$boardGame->is_expansion) {
            throw new \InvalidArgumentException('Board game must be an expansion.');
        }
    }

    /**
     * Validate player count is within limits.
     *
     * @param int $count The player count
     * @return void
     * @throws \InvalidArgumentException If player count is invalid
     */
    public function validatePlayerCount(int $count): void
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('A play must have at least one player.');
        }

        if ($count > 30) {
            throw new \InvalidArgumentException('A play cannot have more than 30 players.');
        }
    }

    /**
     * Get the default group for a user.
     *
     * @param User $user The user
     * @return Group|null The default group or null
     */
    public function getDefaultGroupForUser(User $user): ?Group
    {
        if ($user->default_group_id === null) {
            return null;
        }

        return Group::find($user->default_group_id);
    }

    /**
     * Queue BGG sync job if requested.
     *
     * @param BoardGamePlay $play The play to sync
     * @param string|null $bggUsername Optional BGG username for provided credentials method
     * @param string|null $bggPassword Optional BGG password for provided credentials method
     * @return void
     */
    public function queueBggSyncIfRequested(BoardGamePlay $play, ?string $bggUsername = null, ?string $bggPassword = null): void
    {
        if (!$play->sync_to_bgg) {
            return;
        }

        // Update status to pending
        $play->update([
            'bgg_sync_to_status' => 'pending',
        ]);

        // Queue the sync job with delay to respect rate limits
        SyncBoardGamePlayToBoardGameGeekJob::dispatch($play->id, $bggUsername, $bggPassword)
            ->delay(now()->addSeconds(2));
    }
}

