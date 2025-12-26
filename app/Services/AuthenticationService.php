<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Service class for handling authentication business logic.
 *
 * This service encapsulates authentication operations to keep controllers thin
 * and business logic separated from HTTP concerns.
 */
class AuthenticationService
{
    /**
     * Authenticate a user with email and password.
     *
     * @param string $email
     * @param string $password
     * @param bool $remember
     * @return User
     * @throws ValidationException
     */
    public function authenticateUser(string $email, string $password, bool $remember = false): User
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $user;
    }

    /**
     * Register a new user.
     *
     * @param array<string, mixed> $userData
     * @return User
     */
    public function registerUser(array $userData): User
    {
        $userData['password'] = Hash::make($userData['password']);

        return User::create($userData);
    }

    /**
     * Create an API token for a user.
     *
     * @param User $user
     * @param string|null $tokenName
     * @return string
     */
    public function createApiToken(User $user, ?string $tokenName = null): string
    {
        $tokenName = $tokenName ?? 'api-token';

        return $user->createToken($tokenName)->plainTextToken;
    }

    /**
     * Revoke all API tokens for a user.
     *
     * @param User $user
     * @return void
     */
    public function revokeAllApiTokens(User $user): void
    {
        $user->tokens()->delete();
    }
}

