<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\BoardGamePlayPlayer;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for handling the authenticated dashboard.
 *
 * This controller displays the main dashboard page with board game statistics
 * and information for authenticated users.
 */
class DashboardController extends Controller
{
    /**
     * Display the dashboard page with random board games and user statistics.
     */
    public function index(): Response
    {
        $user = Auth::user();
        
        $randomGames = BoardGame::inRandomOrder()
            ->limit(3)
            ->get([
                'id',
                'name',
                'min_players',
                'max_players',
                'playing_time_minutes',
                'year_published',
                'publisher',
                'bgg_rating',
                'complexity_rating',
                'thumbnail_url',
                'is_expansion',
            ]);

        // User statistics: total games played and games won (exclude duplicate plays)
        $userStatistics = [
            'total_games_played' => BoardGamePlayPlayer::where('user_id', $user->id)
                ->whereHas('boardGamePlay', function ($query) {
                    $query->notExcluded();
                })
                ->count(),
            'total_games_won' => BoardGamePlayPlayer::where('user_id', $user->id)
                ->where('is_winner', true)
                ->whereHas('boardGamePlay', function ($query) {
                    $query->notExcluded();
                })
                ->count(),
        ];

        // Last games played by the user (where user participated as a player, exclude duplicates)
        $lastUserPlays = BoardGamePlay::whereHas('players', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->notExcluded()
            ->with([
                'boardGame:id,name,thumbnail_url',
                'players' => function ($query) {
                    $query->with('user:id,name')
                        ->orderBy('position')
                        ->orderBy('score', 'desc');
                },
            ])
            ->orderBy('played_at', 'desc')
            ->limit(10)
            ->get([
                'id',
                'board_game_id',
                'played_at',
                'location',
                'game_length_minutes',
            ]);

        // Last games played by the group (user's default group)
        $defaultGroupId = $user->getDefaultGroupIdOrFirst();
        $lastGroupPlays = collect([]);
        
        if ($defaultGroupId) {
            $lastGroupPlays = BoardGamePlay::where('group_id', $defaultGroupId)
                ->notExcluded()
                ->with([
                    'boardGame:id,name,thumbnail_url',
                    'players' => function ($query) {
                        $query->with('user:id,name')
                            ->orderBy('position')
                            ->orderBy('score', 'desc');
                    },
                ])
                ->orderBy('played_at', 'desc')
                ->limit(10)
                ->get([
                    'id',
                    'board_game_id',
                    'played_at',
                    'location',
                    'game_length_minutes',
                ]);
        }

        return Inertia::render('Dashboard', [
            'games' => $randomGames,
            'userStatistics' => $userStatistics,
            'lastUserPlays' => $lastUserPlays,
            'lastGroupPlays' => $lastGroupPlays,
            'currentUserId' => $user->id,
        ]);
    }
}

