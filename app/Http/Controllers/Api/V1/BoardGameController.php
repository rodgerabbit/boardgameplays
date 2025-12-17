<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\BoardGameResource;
use App\Models\BoardGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BoardGameController for handling board game API requests.
 *
 * This controller provides read-only access to board game information.
 * Only index and show methods are available - no write operations.
 */
class BoardGameController extends BaseApiController
{
    /**
     * Display a listing of board games.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = BoardGame::query();

        // Optional: Add search/filtering logic here in the future
        // For now, just return all board games with pagination

        $perPage = min((int) $request->get('per_page', 15), 100);
        $boardGames = $query->orderBy('name')->paginate($perPage);

        return BoardGameResource::collection($boardGames)->response();
    }

    /**
     * Display the specified board game.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $boardGame = BoardGame::find($id);

        if ($boardGame === null) {
            return $this->errorResponse(
                'Board game not found',
                404
            );
        }

        return $this->successResponse(
            new BoardGameResource($boardGame)
        );
    }
}
