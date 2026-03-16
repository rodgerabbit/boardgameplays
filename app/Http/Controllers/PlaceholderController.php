<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders placeholder pages for sections not yet implemented.
 */
class PlaceholderController extends Controller
{
    public function groups(): Response
    {
        return Inertia::render('Placeholder', ['title' => 'Groups']);
    }

    public function collection(): Response
    {
        return Inertia::render('Placeholder', ['title' => 'Collection']);
    }

    public function playLog(): Response
    {
        return Inertia::render('Placeholder', ['title' => 'Play Log']);
    }

    public function statistics(): Response
    {
        return Inertia::render('Placeholder', ['title' => 'Statistics']);
    }

    public function boardgames(): Response
    {
        return Inertia::render('Placeholder', ['title' => 'Boardgames']);
    }

    /**
     * Placeholder for a single board game detail page. To be further defined later.
     */
    public function boardGameShow(string $id): Response
    {
        return Inertia::render('Placeholder', ['title' => 'Board Game', 'boardGameId' => $id]);
    }
}
