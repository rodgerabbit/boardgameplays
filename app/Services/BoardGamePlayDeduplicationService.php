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
            foreach ($duplicateGroups as $duplicateGroup) {
                if ($duplicateGroup->count() < 2) {
                    continue;
                }

                // Ensure all plays have players loaded before determining leading play
                $duplicateGroup->each(function (BoardGamePlay $play) {
                    if (!$play->relationLoaded('players')) {
                        $play->load('players');
                    }
                });

                // Determine the leading play
                $leadingPlay = $this->determineLeadingPlay($duplicateGroup);
                $excludedPlays = $duplicateGroup->where('id', '!=', $leadingPlay->id);

                // Mark excluded plays
                $this->markExcludedPlays($leadingPlay, $excludedPlays);

                // Ensure leading play is not excluded
                if ($leadingPlay->is_excluded) {
                    $this->clearExclusion($leadingPlay);
                }
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

                if ($leadingPlay->is_excluded) {
                    $this->clearExclusion($leadingPlay);
                }
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
     * Returns an array of collections, where each collection contains plays that are duplicates.
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, BoardGamePlay>|\Illuminate\Support\Collection<int, BoardGamePlay> $plays The plays to analyze
     * @return array<int, \Illuminate\Support\Collection<int, BoardGamePlay>> Array of duplicate groups
     */
    public function identifyDuplicateGroups(\Illuminate\Support\Collection $plays): array
    {
        $duplicateGroups = [];
        $processed = [];

        foreach ($plays as $play) {
            if (isset($processed[$play->id])) {
                continue;
            }

            $duplicateGroup = collect([$play]);
            $processed[$play->id] = true;

            // Find all plays with same participants but different creators
            foreach ($plays as $otherPlay) {
                if ($play->id === $otherPlay->id || isset($processed[$otherPlay->id])) {
                    continue;
                }

                // Must have different creators
                if ($play->created_by_user_id === $otherPlay->created_by_user_id) {
                    continue;
                }

                // Must have same participants
                if ($this->hasSameParticipants($play, $otherPlay)) {
                    $duplicateGroup->push($otherPlay);
                    $processed[$otherPlay->id] = true;
                }
            }

            if ($duplicateGroup->count() >= 2) {
                $duplicateGroups[] = $duplicateGroup;
            }
        }

        return $duplicateGroups;
    }

    /**
     * Determine the leading play from a collection of duplicate plays.
     *
     * Selection priority:
     * 1. Earliest created_at
     * 2. Lowest bgg_play_id (if both have it)
     * 3. Play with more details (comments, scores)
     *
     * @param \Illuminate\Support\Collection<int, BoardGamePlay>|\Illuminate\Database\Eloquent\Collection<int, BoardGamePlay> $duplicatePlays The duplicate plays
     * @return BoardGamePlay The leading play
     */
    public function determineLeadingPlay(\Illuminate\Support\Collection $duplicatePlays): BoardGamePlay
    {
        // Sort by created_at, then by bgg_play_id
        $sorted = $duplicatePlays->sortBy(function (BoardGamePlay $play) {
            return [
                $play->created_at->timestamp,
                $play->bgg_play_id !== null ? (int) $play->bgg_play_id : PHP_INT_MAX,
            ];
        });

        $leading = $sorted->first();

        // Check if there are multiple plays with the same priority (same created_at and bgg_play_id)
        $samePriority = $sorted->filter(function (BoardGamePlay $play) use ($leading) {
            return $play->created_at->equalTo($leading->created_at) &&
                   ($play->bgg_play_id ?? PHP_INT_MAX) === ($leading->bgg_play_id ?? PHP_INT_MAX);
        });

        if ($samePriority->count() > 1) {
            // Reload all plays from database with players to ensure fresh data
            // Order by ID to ensure consistent ordering
            $playIds = $samePriority->pluck('id')->toArray();
            $freshPlays = BoardGamePlay::whereIn('id', $playIds)
                ->with('players')
                ->orderBy('id')
                ->get();

            // Calculate detail score function - must match test calculation exactly
            $calculateDetailScore = function (BoardGamePlay $play): int {
                $detailScore = 0;

                // Comment adds significant value (match test: !empty($play->comment))
                if (!empty($play->comment)) {
                    $detailScore += 10;
                }

                // Scores add value - ensure players are loaded
                if (!$play->relationLoaded('players')) {
                    $play->load('players');
                }
                // Match test: whereNotNull('score')->count() > 0
                $playersWithScores = $play->players->whereNotNull('score')->count();
                if ($playersWithScores > 0) {
                    $detailScore += 5;
                }

                // Game length adds value
                if ($play->game_length_minutes !== null) {
                    $detailScore += 2;
                }

                return $detailScore;
            };

            // Calculate scores for all plays and find maximum
            // Use a more explicit approach to ensure deterministic selection
            $bestPlay = null;
            $bestScore = -1;
            $bestId = PHP_INT_MAX;

            foreach ($freshPlays as $play) {
                $score = $calculateDetailScore($play);
                
                // If this play has a higher detail score, it's the new best
                if ($score > $bestScore) {
                    $bestPlay = $play;
                    $bestScore = $score;
                    $bestId = $play->id;
                } elseif ($score === $bestScore && $score > 0) {
                    // If scores are equal and non-zero, prefer the play with lower ID
                    if ($play->id < $bestId) {
                        $bestPlay = $play;
                        $bestId = $play->id;
                    }
                }
            }

            $leading = $bestPlay ?? $freshPlays->first();
        }

        return $leading;
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
                    'user_id' => $player->user_id,
                    'bgg_username' => $player->board_game_geek_username,
                    'guest_name' => $player->guest_name,
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
