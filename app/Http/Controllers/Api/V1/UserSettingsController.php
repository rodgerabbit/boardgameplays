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
     * Get the authenticated user's settings.
     *
     * Retrieve the current user's settings including default group,
     * theme preference, and play notification delay.
     *
     * @return JsonResponse
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
     * Update the authenticated user's settings.
     *
     * Update one or more of the user's settings. Only provided fields will be updated.
     *
     * @param UpdateUserSettingsRequest $request
     * @return JsonResponse
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
