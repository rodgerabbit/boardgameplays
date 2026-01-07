<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @group Authentication
 *
 * APIs for user authentication and token management.
 * This API uses Laravel Sanctum for token-based authentication.
 */
class ApiAuthController extends BaseApiController
{
    /**
     * Create a new API authentication controller instance.
     */
    public function __construct(
        private readonly AuthenticationService $authenticationService
    ) {
    }

    /**
     * Login
     *
     * Authenticate a user and return an API token.
     *
     * @bodyParam email string required The user's email address. Example: user@example.com
     * @bodyParam password string required The user's password. Example: password123
     * @bodyParam remember boolean Whether to remember the user. Example: true
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Login successful",
     *   "data": {
     *     "user": {
     *       "id": 1,
     *       "name": "John Doe",
     *       "email": "user@example.com",
     *       "email_verified_at": "2025-12-17T19:52:30+00:00",
     *       "created_at": "2025-12-17T19:52:30+00:00",
     *       "updated_at": "2025-12-17T19:52:30+00:00"
     *     },
     *     "token": "1|abcdefghijklmnopqrstuvwxyz1234567890"
     *   }
     * }
     *
     * @response 401 {
     *   "success": false,
     *   "message": "The provided credentials are incorrect.",
     *   "errors": {
     *     "email": ["The provided credentials are incorrect."]
     *   }
     * }
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $user = $this->authenticationService->authenticateUser(
                $validated['email'],
                $validated['password']
            );

            $token = $this->authenticationService->createApiToken($user, 'api-login');

            return $this->successResponse([
                'user' => new UserResource($user),
                'token' => $token,
            ], 'Login successful', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'The provided credentials are incorrect.',
                401,
                ['email' => ['The provided credentials are incorrect.']]
            );
        }
    }

    /**
     * Register
     *
     * Register a new user account and return an API token.
     *
     * @bodyParam name string required The user's full name. Example: John Doe
     * @bodyParam email string required The user's email address. Must be unique. Example: user@example.com
     * @bodyParam password string required The user's password. Must be confirmed. Example: password123
     * @bodyParam password_confirmation string required The password confirmation. Must match password. Example: password123
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Registration successful",
     *   "data": {
     *     "user": {
     *       "id": 1,
     *       "name": "John Doe",
     *       "email": "user@example.com",
     *       "email_verified_at": null,
     *       "created_at": "2025-12-17T19:52:30+00:00",
     *       "updated_at": "2025-12-17T19:52:30+00:00"
     *     },
     *     "token": "1|abcdefghijklmnopqrstuvwxyz1234567890"
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "email": ["The email has already been taken."],
     *     "password": ["The password confirmation does not match."]
     *   }
     * }
     *
     * @unauthenticated
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $this->authenticationService->registerUser($validated);

        $token = $this->authenticationService->createApiToken($user, 'api-register');

        return $this->successResponse([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Registration successful', 201);
    }

    /**
     * Logout
     *
     * Revoke the current API token and log out the authenticated user.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Logout successful",
     *   "data": null
     * }
     *
     * @response 401 {
     *   "success": false,
     *   "message": "Unauthenticated"
     * }
     *
     * @authenticated
     */
    public function logout(): JsonResponse
    {
        $user = Auth::user();

        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return $this->successResponse(null, 'Logout successful', 200);
    }

    /**
     * Get authenticated user
     *
     * Retrieve the currently authenticated user's information.
     *
     * @response 200 {
     *   "success": true,
     *   "message": null,
     *   "data": {
     *     "user": {
     *       "id": 1,
     *       "name": "John Doe",
     *       "email": "user@example.com",
     *       "email_verified_at": "2025-12-17T19:52:30+00:00",
     *       "created_at": "2025-12-17T19:52:30+00:00",
     *       "updated_at": "2025-12-17T19:52:30+00:00"
     *     }
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
    public function me(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        return $this->successResponse([
            'user' => new UserResource($user),
        ], null, 200);
    }
}



