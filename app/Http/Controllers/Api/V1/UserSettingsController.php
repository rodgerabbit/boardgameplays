<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\UpdateUserSettingsRequest;
use App\Http\Resources\UserSettingsResource;
use App\Services\UserSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @group User Settings
 *
 * APIs for managing user settings.
 */
class UserSettingsController extends BaseApiController
{
    /**
     * Create a new UserSettingsController instance.
     */
    public function __construct(
        private readonly UserSettingsService $userSettingsService,
    ) {
    }

    /**
     * Get user settings
     *
     * Retrieve the authenticated user's settings including default group,
     * theme preference, play notification delay, and BoardGameGeek username.
     *
     * @response 200 {
     *   "success": true,
     *   "message": null,
     *   "data": {
     *     "default_group_id": 1,
     *     "effective_default_group_id": 1,
     *     "theme_preference": "system",
     *     "play_notification_delay_hours": 0,
     *     "board_game_geek_username": null
     *   }
     * }
     *
     * @response 401 {
     *   "success": false,
     *   "message": "Unauthenticated"
     * }
     *
     * @authenticated
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        return $this->successResponse(new UserSettingsResource($user));
    }

    /**
     * Update user settings
     *
     * Update one or more of the authenticated user's settings. Only provided fields will be updated.
     *
     * @bodyParam default_group_id integer nullable The ID of the user's default group. Must be a group the user is a member of. Example: 1
     * @bodyParam theme_preference string nullable The user's theme preference. Must be one of: light, dark, system. Example: dark
     * @bodyParam play_notification_delay_hours integer nullable The delay in hours before sending play notifications. Must be between 0 and 4. Example: 2
     * @bodyParam board_game_geek_username string nullable The user's BoardGameGeek.com username. Example: myusername
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Settings updated successfully.",
     *   "data": {
     *     "default_group_id": 1,
     *     "effective_default_group_id": 1,
     *     "theme_preference": "dark",
     *     "play_notification_delay_hours": 2,
     *     "board_game_geek_username": "myusername"
     *   }
     * }
     *
     * @response 401 {
     *   "success": false,
     *   "message": "Unauthenticated"
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "The selected group does not exist or you are not a member of it.",
     *   "errors": {
     *     "default_group_id": ["The selected group does not exist or you are not a member of it."]
     *   }
     * }
     *
     * @authenticated
     */
    public function update(UpdateUserSettingsRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        try {
            $updatedUser = $this->userSettingsService->updateUserSettings(
                $user,
                $request->validated()
            );

            return $this->successResponse(
                new UserSettingsResource($updatedUser),
                'Settings updated successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
