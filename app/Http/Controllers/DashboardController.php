<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BoardGame;
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
     * Display the dashboard page with random board games.
     */
    public function index(): Response
    {
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
            ]);

        return Inertia::render('Dashboard', [
            'games' => $randomGames,
        ]);
    }
}

