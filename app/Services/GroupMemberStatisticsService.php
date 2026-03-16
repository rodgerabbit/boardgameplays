<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BoardGamePlay;
use App\Models\BoardGamePlayPlayer;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Support\Facades\DB;

/**
 * Service for computing per-member statistics within a group (including H-index).
 */
class GroupMemberStatisticsService
{
    /**
     * Get statistics for each group member: games played, won, win %, unique games, time, last active, H-index games, H-index players.
     *
     * @return array<int, array{group_member_id: int, user_id: int, user_name: string, display_name: string|null, total_games_played: int, total_games_won: int, win_percentage: float, unique_games_played: int, total_minutes_played: int, last_active_at: string|null, h_index_games: int, h_index_players: int}>
     */
    public function getMemberStatistics(Group $group): array
    {
        $members = $group->groupMembers()->with('user')->get();
        $result = [];

        foreach ($members as $member) {
            $userId = (int) $member->user_id;
            $plays = BoardGamePlay::query()
                ->forGroup($group)
                ->notExcluded()
                ->whereHas('players', fn ($q) => $q->where('user_id', $userId))
                ->with(['players', 'boardGame'])
                ->get();

            $totalGamesPlayed = $plays->count();
            $totalGamesWon = $plays->filter(fn ($p) => $p->players->firstWhere('user_id', $userId)?->is_winner)->count();
            $winPercentage = $totalGamesPlayed > 0 ? round($totalGamesWon / $totalGamesPlayed * 100, 1) : 0.0;
            $uniqueGamesPlayed = $plays->pluck('board_game_id')->unique()->count();
            $totalMinutesPlayed = $plays->sum('game_length_minutes') ?? 0;
            $lastPlayedAt = $plays->isEmpty() ? null : $plays->max('played_at');

            $gameCounts = [];
            foreach ($plays as $play) {
                $gid = $play->board_game_id;
                $gameCounts[$gid] = ($gameCounts[$gid] ?? 0) + 1;
            }
            $hIndexGames = $this->computeHIndex($gameCounts);

            $playerCounts = $this->getCoPlayerCounts($plays, $userId);
            $hIndexPlayers = $this->computeHIndex($playerCounts);

            $result[] = [
                'group_member_id' => $member->id,
                'user_id' => $userId,
                'user_name' => $member->user?->name ?? 'Unknown',
                'display_name' => $member->display_name,
                'total_games_played' => $totalGamesPlayed,
                'total_games_won' => $totalGamesWon,
                'win_percentage' => $winPercentage,
                'unique_games_played' => $uniqueGamesPlayed,
                'total_minutes_played' => (int) $totalMinutesPlayed,
                'last_active_at' => $lastPlayedAt?->toIso8601String(),
                'h_index_games' => $hIndexGames,
                'h_index_players' => $hIndexPlayers,
            ];
        }

        usort($result, fn ($a, $b) => $b['total_games_played'] <=> $a['total_games_played']);

        return $result;
    }

    /**
     * H-index: largest h such that there are at least h items each with count >= h.
     *
     * @param array<int|string, int> $counts Map of id => count
     */
    private function computeHIndex(array $counts): int
    {
        if (empty($counts)) {
            return 0;
        }
        $sorted = collect($counts)->map(fn ($c) => (int) $c)->sortDesc()->values()->all();
        $h = 0;
        foreach ($sorted as $i => $c) {
            $k = $i + 1;
            if ($c >= $k) {
                $h = $k;
            } else {
                break;
            }
        }
        return $h;
    }

    /**
     * For plays the user participated in, count how many times they played with each other player (by user_id or guest identifier).
     *
     * @param \Illuminate\Support\Collection<int, BoardGamePlay> $plays
     * @return array<string, int> Map of player_identifier => count
     */
    private function getCoPlayerCounts($plays, int $userId): array
    {
        $counts = [];
        foreach ($plays as $play) {
            foreach ($play->players as $player) {
                if ((int) $player->user_id === $userId) {
                    continue;
                }
                $key = $player->user_id !== null
                    ? 'u' . $player->user_id
                    : 'g' . ($player->guest_name ?? $player->board_game_geek_username ?? 'unknown');
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
        return $counts;
    }
}
