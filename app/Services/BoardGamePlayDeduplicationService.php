<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BoardGamePlay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service class for managing board game play deduplication.
 *
 * This service handles the identification and management of duplicate plays,
 * ensuring that only one play (the "leading" play) is counted in statistics
 * while others are marked as excluded.
 */
class BoardGamePlayDeduplicationService extends BaseService
{
    /**
     * Sync deduplication for a single play.
     *
     * This is the main entry point that handles deduplication when a play
     * is created or updated. It will find duplicates, determine the leading
     * play, and mark others as excluded.
     *
     * @param BoardGamePlay $play The play to sync deduplication for
     * @return void
     */
    public function syncDeduplicationForPlay(BoardGamePlay $play): void
    {
        DB::transaction(function () use ($play): void {
            // Refresh the play to ensure we have the latest data
            $play->refresh();
            $play->load('players'); // Ensure players are loaded

            // If the play doesn't have a group, we can't deduplicate
            if ($play->group_id === null) {
                return;
            }

            // Find all potential duplicates (same boardgame, date, group)
            $potentialDuplicates = $this->findPotentialDuplicates($play);

            if ($potentialDuplicates->count() < 2) {
                // No duplicates found, ensure this play is not excluded
                if ($play->is_excluded) {
                    $this->clearExclusion($play);
                }
                return;
            }

            // Group plays by actual duplicates (same participants, different creators)
            $duplicateGroups = $this->identifyDuplicateGroups($potentialDuplicates);

            // Check if current play is in any duplicate group
            $playIsInDuplicateGroup = false;
            foreach ($duplicateGroups as $duplicateGroup) {
                if ($duplicateGroup->contains('id', $play->id)) {
                    $playIsInDuplicateGroup = true;
                    break;
                }
            }

            // If play is not in any duplicate group but is excluded, clear exclusion
            if (!$playIsInDuplicateGroup && $play->is_excluded) {
                $this->clearExclusion($play);
            }

            // Process each duplicate group
            $syncedPlayWasLeading = false;
            foreach ($duplicateGroups as $duplicateGroup) {
                if ($duplicateGroup->count() < 2) {
                    continue;
                }

                // Ensure all plays have players loaded before determining leading play
                $duplicateGroup->each(function (BoardGamePlay $p) {
                    if (!$p->relationLoaded('players')) {
                        $p->load('players');
                    }
                });

                // Determine the leading play
                $leadingPlay = $this->determineLeadingPlay($duplicateGroup);
                $excludedPlays = $duplicateGroup->where('id', '!=', $leadingPlay->id);

                if ($leadingPlay->id === $play->id) {
                    $syncedPlayWasLeading = true;
                }

                // Mark excluded plays
                $this->markExcludedPlays($leadingPlay, $excludedPlays);

                // Always persist leading play as non-excluded so DB state is correct after sync
                $this->clearExclusion($leadingPlay);
            }

            // Ensure the play we synced is cleared when it was the leading play (same row, possibly different instance)
            if ($syncedPlayWasLeading) {
                $this->clearExclusion($play);
            }
        });
    }

    /**
     * Sync deduplication for a specific scope.
     *
     * This method allows recalculating deduplication for a specific group,
     * board game, or date range. Useful for bulk operations or fixing data.
     *
     * @param int|null $groupId The group ID to process (null for all groups)
     * @param int|null $boardGameId The board game ID to process (null for all games)
     * @param Carbon|null $playedAt The date to process (null for all dates)
     * @return void
     */
    public function syncDeduplicationForGroup(?int $groupId = null, ?int $boardGameId = null, ?Carbon $playedAt = null): void
    {
        $query = BoardGamePlay::query()
            ->whereNotNull('group_id');

        if ($groupId !== null) {
            $query->where('group_id', $groupId);
        }

        if ($boardGameId !== null) {
            $query->where('board_game_id', $boardGameId);
        }

        if ($playedAt !== null) {
            $query->whereDate('played_at', $playedAt);
        }

        $plays = $query->with('players')->get();

        // Clean up any incorrect same-user exclusions (e.g. from before pairing fix)
        $this->clearSameUserExclusionsInScope($groupId, $boardGameId, $playedAt);

        // Group plays by (board_game_id, played_at, group_id) for efficient processing
        $playGroups = $plays->groupBy(function ($play) {
            return sprintf('%d-%s-%d', $play->board_game_id, $play->played_at->toDateString(), $play->group_id);
        });

        foreach ($playGroups as $playGroup) {
            if ($playGroup->count() < 2) {
                continue;
            }

            $duplicateGroups = $this->identifyDuplicateGroups($playGroup);

            foreach ($duplicateGroups as $duplicateGroup) {
                if ($duplicateGroup->count() < 2) {
                    continue;
                }

                $leadingPlay = $this->determineLeadingPlay($duplicateGroup);
                $excludedPlays = $duplicateGroup->where('id', '!=', $leadingPlay->id);

                $this->markExcludedPlays($leadingPlay, $excludedPlays);

                // Always persist leading play as non-excluded so DB state is correct
                $this->clearExclusion($leadingPlay);
            }
        }
    }

    /**
     * Find potential duplicate plays for a given play.
     *
     * Finds all plays that match the criteria for being duplicates:
     * - Same board_game_id
     * - Same played_at date
     * - Same group_id
     *
     * @param BoardGamePlay $play The play to find duplicates for
     * @return \Illuminate\Database\Eloquent\Collection<int, BoardGamePlay> Collection of potential duplicate plays
     */
    public function findPotentialDuplicates(BoardGamePlay $play): \Illuminate\Database\Eloquent\Collection
    {
        $play->load('players'); // Ensure current play has players loaded
        return BoardGamePlay::query()
            ->where('board_game_id', $play->board_game_id)
            ->whereDate('played_at', $play->played_at)
            ->where('group_id', $play->group_id)
            ->where('id', '!=', $play->id)
            ->with('players')
            ->get()
            ->push($play)
            ->unique('id');
    }

    /**
     * Identify duplicate groups from a collection of plays.
     *
     * Groups plays that are actual duplicates (same participants, different creators).
     * When multiple plays from different creators share the same participants (e.g. two
     * users each logged two plays of the same game on the same day), plays are paired
     * by order: 1st play of creator A with 1st of creator B, 2nd with 2nd, etc.
     * Order within creator is by bgg_play_id (asc, null last) then created_at (asc).
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, BoardGamePlay>|\Illuminate\Support\Collection<int, BoardGamePlay> $plays The plays to analyze
     * @return array<int, \Illuminate\Support\Collection<int, BoardGamePlay>> Array of duplicate groups
     */
    public function identifyDuplicateGroups(\Illuminate\Support\Collection $plays): array
    {
        if ($plays->count() < 2) {
            return [];
        }

        // Ensure all plays have players loaded for participant grouping
        $plays->each(function (BoardGamePlay $play) {
            if (!$play->relationLoaded('players')) {
                $play->load('players');
            }
        });

        // 1. Group by participant set (same participants = same real game pool)
        $byParticipantKey = $plays->groupBy(function (BoardGamePlay $play) {
            $participants = $this->normalizeParticipants($play);

            return json_encode($participants);
        });

        $duplicateGroups = [];
        foreach ($byParticipantKey as $participantKey => $playsWithSameParticipants) {
            if ($playsWithSameParticipants->count() < 2) {
                continue;
            }

            // 2. Partition by creator; need at least 2 creators for duplicates
            $byCreator = $playsWithSameParticipants->groupBy('created_by_user_id');
            if ($byCreator->count() < 2) {
                continue;
            }

            // 3. Sort each creator's plays by BGG order (asc)
            $sortKey = function (BoardGamePlay $play) {
                return [
                    $play->bgg_play_id !== null ? (int) $play->bgg_play_id : PHP_INT_MAX,
                    $play->created_at->timestamp,
                    $play->id,
                ];
            };
            $sortedByCreator = $byCreator->map(function ($creatorPlays) use ($sortKey) {
                return $creatorPlays->sortBy($sortKey)->values();
            });

            $maxLength = $sortedByCreator->max(fn ($list) => $list->count());
            if ($maxLength < 1) {
                continue;
            }

            // 4. For each index i, form a group of plays at that index (from every creator that has an i-th play).
            //    So when creators have different counts (e.g. 2, 2, 1), we get: index 0 → 3 plays, index 1 → 2 plays.
            for ($i = 0; $i < $maxLength; $i++) {
                $group = collect();
                foreach ($sortedByCreator as $creatorPlays) {
                    if ($i < $creatorPlays->count()) {
                        $group->push($creatorPlays[$i]);
                    }
                }
                if ($group->count() >= 2) {
                    $duplicateGroups[] = $group;
                }
            }
        }

        return $duplicateGroups;
    }

    /**
     * Determine the leading play from a collection of duplicate plays.
     *
     * Selection priority:
     * 1. Incomplete plays are never chosen as leading (they are always excluded when a complete duplicate exists).
     * 2. Play with more details (location, new player indicator, time, comments, scores)
     * 3. Logged earlier: lowest bgg_play_id when present (BGG order), then earliest created_at
     *
     * @param \Illuminate\Support\Collection<int, BoardGamePlay>|\Illuminate\Database\Eloquent\Collection<int, BoardGamePlay> $duplicatePlays The duplicate plays
     * @return BoardGamePlay The leading play
     */
    public function determineLeadingPlay(\Illuminate\Support\Collection $duplicatePlays): BoardGamePlay
    {
        // Ensure all plays have players loaded for detail score
        $duplicatePlays->each(function (BoardGamePlay $play) {
            if (!$play->relationLoaded('players')) {
                $play->load('players');
            }
        });

        // Sort by: (1) incomplete last (is_incomplete ? 1 : 0), (2) detail score descending, (3) bgg_play_id ascending, (4) created_at, (5) id
        $sorted = $duplicatePlays->sortBy(function (BoardGamePlay $play) {
            $detailScore = $this->calculateDetailScore($play);

            return [
                $play->is_incomplete ? 1 : 0, // incomplete plays last so they are never chosen as leading when a complete duplicate exists
                -$detailScore,
                $play->bgg_play_id !== null ? (int) $play->bgg_play_id : PHP_INT_MAX,
                $play->created_at instanceof \Carbon\Carbon ? $play->created_at->timestamp : (int) strtotime((string) $play->created_at),
                $play->id,
            ];
        })->values();

        return $sorted->first();
    }

    /**
     * Calculate a detail richness score for leading-play preference.
     *
     * Higher score = more details (location, new player indicator, time, comments, scores).
     * Used to prefer the more complete log when deduplicating.
     *
     * @param BoardGamePlay $play The play to score
     * @return int Non-negative detail score
     */
    private function calculateDetailScore(BoardGamePlay $play): int
    {
        $detailScore = 0;

        // Location and game length are weighted above comment (where was it played, how long it took)
        $location = trim((string) ($play->location ?? ''));
        if ($location !== '' && strtolower($location) !== 'unknown') {
            $detailScore += 10;
        }

        if ($play->game_length_minutes !== null) {
            $detailScore += 8;
        }

        // Comment adds value but less than location/game length
        if (!empty($play->comment)) {
            $detailScore += 5;
        }

        if (!$play->relationLoaded('players')) {
            $play->load('players');
        }

        // New player indicator: at least one player marked as new to this game
        $hasNewPlayer = $play->players->where('is_new_player', true)->count() > 0;
        if ($hasNewPlayer) {
            $detailScore += 5;
        }

        // Scores add value
        $playersWithScores = $play->players->whereNotNull('score')->count();
        if ($playersWithScores > 0) {
            $detailScore += 5;
        }

        return $detailScore;
    }

    /**
     * Mark plays as excluded, pointing to the leading play.
     *
     * @param BoardGamePlay $leadingPlay The leading play
     * @param \Illuminate\Support\Collection<int, BoardGamePlay>|\Illuminate\Database\Eloquent\Collection<int, BoardGamePlay> $excludedPlays The plays to mark as excluded
     * @return void
     */
    public function markExcludedPlays(BoardGamePlay $leadingPlay, \Illuminate\Support\Collection $excludedPlays): void
    {
        foreach ($excludedPlays as $excludedPlay) {
            if ($excludedPlay->id === $leadingPlay->id) {
                continue;
            }

            // Never mark a play as excluded when the leading play is from the same user.
            // Duplicates are only across different users (same game/date/participants, different creators).
            if ($excludedPlay->created_by_user_id === $leadingPlay->created_by_user_id) {
                continue;
            }

            $excludedPlay->update([
                'is_excluded' => true,
                'leading_play_id' => $leadingPlay->id,
                'excluded_at' => now(),
                'exclusion_reason' => sprintf(
                    'Duplicate of play #%d (same boardgame, date, and participants, logged by different users)',
                    $leadingPlay->id
                ),
            ]);

            Log::info('Play marked as excluded', [
                'excluded_play_id' => $excludedPlay->id,
                'leading_play_id' => $leadingPlay->id,
                'board_game_id' => $excludedPlay->board_game_id,
                'played_at' => $excludedPlay->played_at->toDateString(),
            ]);
        }
    }

    /**
     * Clear exclusion status from a play.
     *
     * @param BoardGamePlay $play The play to clear exclusion from
     * @return void
     */
    public function clearExclusion(BoardGamePlay $play): void
    {
        $play->update([
            'is_excluded' => false,
            'leading_play_id' => null,
            'excluded_at' => null,
            'exclusion_reason' => null,
        ]);
    }

    /**
     * Clear exclusion from any play that points to a leading play from the same user.
     * Fixes legacy data where same-user plays were incorrectly marked as duplicates.
     */
    private function clearSameUserExclusionsInScope(?int $groupId, ?int $boardGameId, ?Carbon $playedAt): void
    {
        $query = BoardGamePlay::query()
            ->where('is_excluded', true)
            ->whereNotNull('leading_play_id')
            ->whereNotNull('group_id');

        if ($groupId !== null) {
            $query->where('group_id', $groupId);
        }
        if ($boardGameId !== null) {
            $query->where('board_game_id', $boardGameId);
        }
        if ($playedAt !== null) {
            $query->whereDate('played_at', $playedAt);
        }

        $query->whereRaw(
            'created_by_user_id = (SELECT created_by_user_id FROM board_game_plays AS lp WHERE lp.id = board_game_plays.leading_play_id)'
        )->each(function (BoardGamePlay $play): void {
                $wasLeadingPlayId = $play->leading_play_id;
                $this->clearExclusion($play);
                Log::info('Cleared same-user exclusion', [
                    'play_id' => $play->id,
                    'was_leading_play_id' => $wasLeadingPlayId,
                ]);
            });
    }

    /**
     * Normalize participants to a comparable format.
     *
     * Creates a normalized array of participant identifiers that can be compared
     * to determine if two plays have the same participants.
     *
     * @param BoardGamePlay $play The play to normalize participants for
     * @return array<int, array<string, mixed>> Normalized participant array
     */
    private function normalizeParticipants(BoardGamePlay $play): array
    {
        return $play->players
            ->map(function ($player) {
                return [
                    'user_id' => $player->user_id !== null ? (int) $player->user_id : null,
                    'bgg_username' => $player->board_game_geek_username !== null ? trim((string) $player->board_game_geek_username) : null,
                    'guest_name' => $player->guest_name !== null ? trim((string) $player->guest_name) : null,
                ];
            })
            ->sortBy(function ($participant) {
                // Sort by user_id first, then bgg_username, then guest_name
                return sprintf(
                    '%s-%s-%s',
                    $participant['user_id'] ?? '',
                    $participant['bgg_username'] ?? '',
                    $participant['guest_name'] ?? ''
                );
            })
            ->values()
            ->toArray();
    }

    /**
     * Check if two plays have the same participants.
     *
     * Compares normalized participant arrays to determine if the plays
     * have identical participants (by user_id, BGG username, or guest name).
     *
     * @param BoardGamePlay $play1 The first play
     * @param BoardGamePlay $play2 The second play
     * @return bool True if the plays have the same participants
     */
    private function hasSameParticipants(BoardGamePlay $play1, BoardGamePlay $play2): bool
    {
        $participants1 = $this->normalizeParticipants($play1);
        $participants2 = $this->normalizeParticipants($play2);

        if (count($participants1) !== count($participants2)) {
            return false;
        }

        // Compare each participant
        foreach ($participants1 as $index => $participant1) {
            if (!isset($participants2[$index])) {
                return false;
            }

            $participant2 = $participants2[$index];

            // Match by any identifier (user_id, bgg_username, or guest_name)
            $matches = false;

            // Match by user_id if both have it
            if ($participant1['user_id'] !== null && $participant2['user_id'] !== null) {
                $matches = $participant1['user_id'] === $participant2['user_id'];
            }
            // Match by BGG username if both have it
            elseif ($participant1['bgg_username'] !== null && $participant2['bgg_username'] !== null) {
                $matches = $participant1['bgg_username'] === $participant2['bgg_username'];
            }
            // Match by guest name if both have it
            elseif ($participant1['guest_name'] !== null && $participant2['guest_name'] !== null) {
                $matches = $participant1['guest_name'] === $participant2['guest_name'];
            }

            if (!$matches) {
                return false;
            }
        }

        return true;
    }
}
