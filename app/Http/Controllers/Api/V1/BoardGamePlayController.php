<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\BoardGamePlay\StoreBoardGamePlayRequest;
use App\Http\Requests\BoardGamePlay\UpdateBoardGamePlayRequest;
use App\Http\Resources\BoardGamePlayResource;
use App\Models\BoardGamePlay;
use App\Services\BoardGamePlayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Board Game Plays
 *
 * APIs for managing board game plays.
 */
class BoardGamePlayController extends BaseApiController
{
    /**
     * Create a new BoardGamePlayController instance.
     */
    public function __construct(
        private readonly BoardGamePlayService $boardGamePlayService,
    ) {
    }

    /**
     * List board game plays
     *
     * Get a paginated list of board game plays. Only plays the user has access to will be returned.
     *
     * @queryParam per_page integer The number of items per page. Maximum 100. Default: 15. Example: 20
     * @queryParam include string Comma-separated list of relationships to include (board_game, group, creator, players, expansions). Example: board_game,players
     * @queryParam group_id integer Filter by group ID. Example: 1
     * @queryParam board_game_id integer Filter by board game ID. Example: 1
     * @queryParam source string Filter by source (website, boardgamegeek). Example: website
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "board_game_id": 1,
     *       "group_id": 1,
     *       "created_by_user_id": 1,
     *       "played_at": "2025-01-07",
     *       "location": "Home",
     *       "comment": "Great game!",
     *       "game_length_minutes": 90,
     *       "source": "website",
     *       "created_at": "2025-01-07T20:22:14+00:00",
     *       "updated_at": "2025-01-07T20:22:14+00:00"
     *     }
     *   ],
     *   "links": {
     *     "first": "http://localhost/api/v1/board-game-plays?page=1",
     *     "last": "http://localhost/api/v1/board-game-plays?page=10",
     *     "prev": null,
     *     "next": "http://localhost/api/v1/board-game-plays?page=2"
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "from": 1,
     *     "last_page": 10,
     *     "path": "http://localhost/api/v1/board-game-plays",
     *     "per_page": 15,
     *     "to": 15,
     *     "total": 150
     *   }
     * }
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BoardGamePlay::class);

        $user = $request->user();
        $query = BoardGamePlay::query();

        // Filter by user's accessible plays (own plays or plays in user's groups)
        $query->where(function ($q) use ($user) {
            $q->where('created_by_user_id', $user->id)
                ->orWhereIn('group_id', $user->groups()->pluck('groups.id'));
        });

        // Apply filters
        if ($request->has('group_id')) {
            $query->where('group_id', $request->get('group_id'));
        }

        if ($request->has('board_game_id')) {
            $query->where('board_game_id', $request->get('board_game_id'));
        }

        if ($request->has('source')) {
            $query->where('source', $request->get('source'));
        }

        // By default, exclude duplicate plays from statistics
        // Allow including excluded plays via query parameter for admin/debugging purposes
        if (!$request->has('include_excluded') || !$request->boolean('include_excluded')) {
            $query->notExcluded();
        }

        // Handle includes
        $includes = explode(',', $request->get('include', ''));
        if (in_array('board_game', $includes)) {
            $query->with('boardGame');
        }
        if (in_array('group', $includes)) {
            $query->with('group');
        }
        if (in_array('creator', $includes)) {
            $query->with('creator');
        }
        if (in_array('players', $includes)) {
            $query->with('players');
        }
        if (in_array('expansions', $includes)) {
            $query->with('expansions');
        }

        $perPage = min((int) $request->get('per_page', 15), 100);
        $plays = $query->orderBy('played_at', 'desc')->paginate($perPage);

        return BoardGamePlayResource::collection($plays)->response();
    }

    /**
     * Create a new board game play
     *
     * Create a new board game play record with players and optional expansions.
     *
     * @bodyParam board_game_id integer required The ID of the board game (must be a base game, not expansion). Example: 1
     * @bodyParam group_id integer The ID of the group. If not provided, uses user's default group. Example: 1
     * @bodyParam played_at date required The date the game was played. Example: 2025-01-07
     * @bodyParam location string required The location where the game was played. Example: Home
     * @bodyParam comment string Optional comment about the play. Example: Great game!
     * @bodyParam game_length_minutes integer Optional game length in minutes. Example: 90
     * @bodyParam source string required The source of the play (website, boardgamegeek). Example: website
     * @bodyParam expansions array Optional array of expansion IDs. Example: [2, 3]
     * @bodyParam players array required Array of players (1-30 players). Example: [{"user_id": 1, "score": 100, "is_winner": true}]
     * @bodyParam sync_to_board_game_geek boolean Whether to sync this play to BGG. Example: false
     *
     * @response 201 {
     *   "message": "Board game play created successfully.",
     *   "data": {
     *     "id": 1,
     *     "board_game_id": 1,
     *     "played_at": "2025-01-07",
     *     "location": "Home",
     *     "comment": "Great game!",
     *     "game_length_minutes": 90,
     *     "source": "website",
     *     "created_at": "2025-01-07T20:22:14+00:00",
     *     "updated_at": "2025-01-07T20:22:14+00:00"
     *   }
     * }
     *
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "board_game_id": ["The board game must not be an expansion."],
     *     "players": ["The players field is required."]
     *   }
     * }
     *
     * @authenticated
     */
    public function store(StoreBoardGamePlayRequest $request): JsonResponse
    {
        $this->authorize('create', BoardGamePlay::class);
        $user = $request->user();

        $play = DB::transaction(function () use ($request, $user) {
            return $this->boardGamePlayService->createBoardGamePlay($request->validated(), $user);
        });

        return $this->successResponse(
            new BoardGamePlayResource($play->load(['boardGame', 'group', 'creator', 'players', 'expansions'])),
            'Board game play created successfully.',
            201
        );
    }

    /**
     * Get a specific board game play
     *
     * Retrieve detailed information about a specific board game play by its ID.
     *
     * @urlParam id required The ID of the board game play. Example: 1
     * @queryParam include string Comma-separated list of relationships to include (board_game, group, creator, players, expansions). Example: board_game,players
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "board_game_id": 1,
     *     "played_at": "2025-01-07",
     *     "location": "Home",
     *     "comment": "Great game!",
     *     "game_length_minutes": 90,
     *     "source": "website",
     *     "created_at": "2025-01-07T20:22:14+00:00",
     *     "updated_at": "2025-01-07T20:22:14+00:00"
     *   }
     * }
     *
     * @response 403 {
     *   "message": "This action is unauthorized."
     * }
     *
     * @response 404 {
     *   "message": "Board game play not found"
     * }
     *
     * @authenticated
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $play = BoardGamePlay::findOrFail($id);
        $this->authorize('view', $play);

        $includes = explode(',', $request->get('include', ''));
        if (in_array('board_game', $includes)) {
            $play->load('boardGame');
        }
        if (in_array('group', $includes)) {
            $play->load('group');
        }
        if (in_array('creator', $includes)) {
            $play->load('creator');
        }
        if (in_array('players', $includes)) {
            $play->load('players');
        }
        if (in_array('expansions', $includes)) {
            $play->load('expansions');
        }

        return $this->successResponse(new BoardGamePlayResource($play));
    }

    /**
     * Update a board game play
     *
     * Update a board game play's properties.
     *
     * @urlParam id required The ID of the board game play. Example: 1
     * @bodyParam board_game_id integer The ID of the board game (must be a base game, not expansion). Example: 1
     * @bodyParam group_id integer The ID of the group. Example: 1
     * @bodyParam played_at date The date the game was played. Example: 2025-01-07
     * @bodyParam location string The location where the game was played. Example: Home
     * @bodyParam comment string Optional comment about the play. Example: Great game!
     * @bodyParam game_length_minutes integer Optional game length in minutes. Example: 90
     * @bodyParam expansions array Optional array of expansion IDs. Example: [2, 3]
     * @bodyParam players array Array of players (1-30 players). Example: [{"user_id": 1, "score": 100, "is_winner": true}]
     *
     * @response 200 {
     *   "message": "Board game play updated successfully.",
     *   "data": {
     *     "id": 1,
     *     "board_game_id": 1,
     *     "played_at": "2025-01-07",
     *     "location": "Home",
     *     "comment": "Great game!",
     *     "game_length_minutes": 90,
     *     "source": "website",
     *     "created_at": "2025-01-07T20:22:14+00:00",
     *     "updated_at": "2025-01-07T20:22:30+00:00"
     *   }
     * }
     *
     * @response 403 {
     *   "message": "This action is unauthorized."
     * }
     *
     * @response 404 {
     *   "message": "Board game play not found"
     * }
     *
     * @authenticated
     */
    public function update(UpdateBoardGamePlayRequest $request, string $id): JsonResponse
    {
        $play = BoardGamePlay::findOrFail($id);
        $this->authorize('update', $play);

        $play = DB::transaction(function () use ($request, $play) {
            return $this->boardGamePlayService->updateBoardGamePlay($play, $request->validated());
        });

        return $this->successResponse(
            new BoardGamePlayResource($play->load(['boardGame', 'group', 'creator', 'players', 'expansions'])),
            'Board game play updated successfully.'
        );
    }

    /**
     * Delete a board game play
     *
     * Delete a board game play. Only the creator or group admin can delete.
     *
     * @urlParam id required The ID of the board game play. Example: 1
     *
     * @response 200 {
     *   "message": "Board game play deleted successfully.",
     *   "data": null
     * }
     *
     * @response 403 {
     *   "message": "This action is unauthorized."
     * }
     *
     * @response 404 {
     *   "message": "Board game play not found"
     * }
     *
     * @authenticated
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $play = BoardGamePlay::findOrFail($id);
        $this->authorize('delete', $play);

        DB::transaction(function () use ($play): void {
            $this->boardGamePlayService->deleteBoardGamePlay($play);
        });

        return $this->successResponse(
            null,
            'Board game play deleted successfully.'
        );
    }
}

