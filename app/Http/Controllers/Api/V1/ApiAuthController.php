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
 * Controller for handling API-based authentication (token-based).
 *
 * This controller handles login, registration, and logout for the API
 * using Laravel Sanctum token-based authentication.
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
     * Handle a login request and return an API token.
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
     * Handle a registration request and return an API token.
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
     * Handle a logout request and revoke the current API token.
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
     * Get the authenticated user.
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

