<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\Group;
use Illuminate\Support\Facades\DB;

/**
 * Service for computing group-level statistics for the overview and other tabs.
 */
class GroupStatisticsService
{
    private const MAX_DONUT_SLICES = 5;

    private const OTHER_LABEL = 'Other';

    private const UNKNOWN_LOCATION_LABEL = 'Unknown';

    /**
     * Get monthly play counts per year for the last 3 years.
     *
     * @return array<string, array<int, int>> e.g. ["2023" => [0,1,...], "2024" => [...], "2025" => [...]]
     */
    public function getMonthlyActivityByYear(Group $group): array
    {
        $threeYearsAgo = now()->subYears(3)->startOfYear();
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $rows = BoardGamePlay::query()
                ->forGroup($group)
                ->notExcluded()
                ->where('played_at', '>=', $threeYearsAgo)
                ->selectRaw('EXTRACT(YEAR FROM played_at)::integer as year, EXTRACT(MONTH FROM played_at)::integer as month, COUNT(*) as count')
                ->groupByRaw('EXTRACT(YEAR FROM played_at), EXTRACT(MONTH FROM played_at)')
                ->orderBy('year')
                ->orderBy('month')
                ->get();
        } else {
            $rows = BoardGamePlay::query()
                ->forGroup($group)
                ->notExcluded()
                ->where('played_at', '>=', $threeYearsAgo)
                ->selectRaw('YEAR(played_at) as year, MONTH(played_at) as month, COUNT(*) as count')
                ->groupByRaw('YEAR(played_at), MONTH(played_at)')
                ->orderBy('year')
                ->orderBy('month')
                ->get();
        }

        $years = [];
        foreach (range((int) $threeYearsAgo->format('Y'), (int) now()->format('Y')) as $y) {
            $years[(string) $y] = array_fill(1, 12, 0);
        }

        foreach ($rows as $row) {
            $y = (string) $row->year;
            if (isset($years[$y])) {
                $years[$y][(int) $row->month] = (int) $row->count;
            }
        }

        return $years;
    }

    /**
     * Get location distribution for donut: display name -> play count.
     * Uses group_settings.location_aliases when present; otherwise raw location.
     * Limited to MAX_DONUT_SLICES top locations; the rest (including "Unknown") are grouped as "Other".
     *
     * @return array<int, array{name: string, count: int}>
     */
    public function getLocationDistribution(Group $group): array
    {
        $plays = BoardGamePlay::query()
            ->forGroup($group)
            ->notExcluded()
            ->select('location')
            ->get();

        $aliases = $group->group_settings['location_aliases'] ?? [];
        $map = [];
        foreach ($aliases as $alias) {
            $displayName = $alias['display_name'] ?? '';
            $raw = $alias['raw_locations'] ?? [];
            foreach ($raw as $r) {
                $map[trim((string) $r)] = $displayName;
            }
        }

        $counts = [];
        foreach ($plays as $play) {
            $loc = trim($play->location);
            $name = $map[$loc] ?? $loc;
            if ($name === '') {
                $name = self::UNKNOWN_LOCATION_LABEL;
            }
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        $unknownCount = $counts[self::UNKNOWN_LOCATION_LABEL] ?? 0;
        unset($counts[self::UNKNOWN_LOCATION_LABEL]);

        $sorted = [];
        foreach ($counts as $name => $count) {
            $sorted[] = ['name' => $name, 'count' => $count];
        }
        usort($sorted, fn ($a, $b) => $b['count'] <=> $a['count']);

        $top = array_slice($sorted, 0, self::MAX_DONUT_SLICES);
        $restCount = array_sum(array_column(array_slice($sorted, self::MAX_DONUT_SLICES), 'count')) + $unknownCount;
        if ($restCount > 0) {
            $top[] = ['name' => self::OTHER_LABEL, 'count' => $restCount];
        }

        return $top;
    }

    /**
     * Get top N games by total play time (minutes). Returns game id, name, thumbnail_url, total_minutes.
     *
     * @return array<int, array{board_game_id: int, name: string, thumbnail_url: string|null, total_minutes: int}>
     */
    public function getTopGamesByTime(Group $group, int $limit = 10): array
    {
        $rows = BoardGamePlay::query()
            ->forGroup($group)
            ->notExcluded()
            ->join('board_games', 'board_game_plays.board_game_id', '=', 'board_games.id')
            ->select('board_game_plays.board_game_id', 'board_games.name', 'board_games.thumbnail_url')
            ->selectRaw('COALESCE(SUM(board_game_plays.game_length_minutes), 0) as total_minutes')
            ->groupBy('board_game_plays.board_game_id', 'board_games.name', 'board_games.thumbnail_url')
            ->orderByDesc('total_minutes')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'board_game_id' => (int) $r->board_game_id,
            'name' => $r->name,
            'thumbnail_url' => $r->thumbnail_url,
            'total_minutes' => (int) $r->total_minutes,
        ])->all();
    }

    /**
     * Get game category distribution for donut from board game categories (BGG boardgamecategory).
     * Each play is counted once per category of its game (games with multiple categories contribute to each).
     * Games with no categories are counted as "Uncategorized".
     * Limited to MAX_DONUT_SLICES top categories; the rest are grouped as "Other".
     *
     * @return array<int, array{name: string, count: int}>
     */
    public function getCategoryDistribution(Group $group): array
    {
        $plays = BoardGamePlay::query()
            ->forGroup($group)
            ->notExcluded()
            ->get(['id', 'board_game_id']);

        if ($plays->isEmpty()) {
            return [];
        }

        $boardGameIds = $plays->pluck('board_game_id')->unique()->values()->all();
        $boardGames = BoardGame::query()
            ->whereIn('id', $boardGameIds)
            ->get(['id', 'categories'])
            ->keyBy('id');

        $categoryCounts = [];
        foreach ($plays as $play) {
            $game = $boardGames->get($play->board_game_id);
            $categories = $game?->categories ?? [];
            if (! is_array($categories) || empty($categories)) {
                $categoryCounts['Uncategorized'] = ($categoryCounts['Uncategorized'] ?? 0) + 1;
                continue;
            }
            foreach ($categories as $cat) {
                $name = is_array($cat) ? (isset($cat['name']) ? trim((string) $cat['name']) : '') : '';
                if ($name === '') {
                    $name = 'Uncategorized';
                }
                $categoryCounts[$name] = ($categoryCounts[$name] ?? 0) + 1;
            }
        }

        $sorted = [];
        foreach ($categoryCounts as $name => $count) {
            $sorted[] = ['name' => $name, 'count' => $count];
        }
        usort($sorted, fn ($a, $b) => $b['count'] <=> $a['count']);

        $top = array_slice($sorted, 0, self::MAX_DONUT_SLICES);
        $rest = array_slice($sorted, self::MAX_DONUT_SLICES);
        $restCount = array_sum(array_column($rest, 'count'));
        if ($restCount > 0) {
            $top[] = ['name' => self::OTHER_LABEL, 'count' => $restCount];
        }

        return $top;
    }

    /**
     * Get paginated list of games played by the group, sorted by play count (most played first).
     *
     * @param  string|null  $search  Optional search string to filter games by name (case-insensitive).
     * @return array{data: array, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function getGamesPlayedByGroup(Group $group, int $perPage = 15, int $page = 1, ?string $search = null): array
    {
        $baseQuery = BoardGamePlay::query()
            ->forGroup($group)
            ->notExcluded()
            ->join('board_games', 'board_game_plays.board_game_id', '=', 'board_games.id');

        if ($search !== null && $search !== '') {
            $searchTerm = '%' . trim($search) . '%';
            $baseQuery->where('board_games.name', 'ilike', $searchTerm);
        }

        $subQuery = (clone $baseQuery)->select('board_game_plays.board_game_id')->groupBy('board_game_plays.board_game_id');
        $total = DB::table(DB::raw('(' . $subQuery->toSql() . ') as t'))->mergeBindings($subQuery->getQuery())->count();

        $items = (clone $baseQuery)
            ->select('board_game_plays.board_game_id', 'board_games.name', 'board_games.thumbnail_url')
            ->selectRaw('COUNT(*) as play_count')
            ->selectRaw('COALESCE(SUM(board_game_plays.game_length_minutes), 0) as total_minutes')
            ->groupBy('board_game_plays.board_game_id', 'board_games.name', 'board_games.thumbnail_url')
            ->orderByDesc('play_count')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $data = $items->map(fn ($r) => [
            'board_game_id' => (int) $r->board_game_id,
            'name' => $r->name,
            'thumbnail_url' => $r->thumbnail_url,
            'play_count' => (int) $r->play_count,
            'total_minutes' => (int) $r->total_minutes,
        ])->all();

        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage ?: 1,
        ];
    }
}
