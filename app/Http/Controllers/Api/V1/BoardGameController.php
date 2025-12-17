<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\BoardGameResource;
use App\Models\BoardGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Board Games
 *
 * APIs for retrieving board game information.
 * This API provides read-only access to board game data. No write operations are available.
 */
class BoardGameController extends BaseApiController
{
    /**
     * List all board games
     *
     * Get a paginated list of all board games in the system.
     *
     * @queryParam per_page integer The number of items per page. Maximum 100. Default: 15. Example: 20
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Catan",
     *       "description": "Players try to be the dominant force on the island of Catan by building settlements, cities, and roads.",
     *       "min_players": 3,
     *       "max_players": 4,
     *       "playing_time_minutes": 90,
     *       "year_published": 1995,
     *       "publisher": "Catan Studio",
     *       "designer": "Klaus Teuber",
     *       "image_url": "https://example.com/catan.jpg",
     *       "bgg_id": "13",
     *       "created_at": "2025-12-17T19:52:30+00:00",
     *       "updated_at": "2025-12-17T19:52:30+00:00"
     *     }
     *   ],
     *   "links": {
     *     "first": "http://localhost:8080/api/v1/board-games?page=1",
     *     "last": "http://localhost:8080/api/v1/board-games?page=10",
     *     "prev": null,
     *     "next": "http://localhost:8080/api/v1/board-games?page=2"
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "from": 1,
     *     "last_page": 10,
     *     "path": "http://localhost:8080/api/v1/board-games",
     *     "per_page": 15,
     *     "to": 15,
     *     "total": 150
     *   }
     * }
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
     * Get a specific board game
     *
     * Retrieve detailed information about a specific board game by its ID.
     *
     * @urlParam id required The ID of the board game. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Catan",
     *     "description": "Players try to be the dominant force on the island of Catan by building settlements, cities, and roads.",
     *     "min_players": 3,
     *     "max_players": 4,
     *     "playing_time_minutes": 90,
     *     "year_published": 1995,
     *     "publisher": "Catan Studio",
     *     "designer": "Klaus Teuber",
     *     "image_url": "https://example.com/catan.jpg",
     *     "bgg_id": "13",
     *     "created_at": "2025-12-17T19:52:30+00:00",
     *     "updated_at": "2025-12-17T19:52:30+00:00"
     *   }
     * }
     *
     * @response 404 {
     *   "message": "Board game not found"
     * }
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
