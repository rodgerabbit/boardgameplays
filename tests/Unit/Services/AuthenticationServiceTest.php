<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Unit tests for AuthenticationService.
 *
 * These tests verify the business logic of authentication operations,
 * including user authentication, registration, and token management.
 */
class AuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthenticationService $authenticationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticationService = new AuthenticationService();
    }

    /**
     * Test that authenticateUser successfully authenticates a user with correct credentials.
     */
    public function test_authenticate_user_succeeds_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $authenticatedUser = $this->authenticationService->authenticateUser(
            'test@example.com',
            'password123'
        );

        $this->assertInstanceOf(User::class, $authenticatedUser);
        $this->assertEquals($user->id, $authenticatedUser->id);
        $this->assertEquals($user->email, $authenticatedUser->email);
    }

    /**
     * Test that authenticateUser throws ValidationException with incorrect email.
     */
    public function test_authenticate_user_throws_exception_with_incorrect_email(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The provided credentials are incorrect.');

        $this->authenticationService->authenticateUser(
            'wrong@example.com',
            'password123'
        );
    }

    /**
     * Test that authenticateUser throws ValidationException with incorrect password.
     */
    public function test_authenticate_user_throws_exception_with_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The provided credentials are incorrect.');

        $this->authenticationService->authenticateUser(
            'test@example.com',
            'wrongpassword'
        );
    }

    /**
     * Test that registerUser creates a new user with hashed password.
     */
    public function test_register_user_creates_user_with_hashed_password(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $user = $this->authenticationService->registerUser($userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    /**
     * Test that createApiToken generates a token for a user.
     */
    public function test_create_api_token_generates_token_for_user(): void
    {
        $user = User::factory()->create();

        $token = $this->authenticationService->createApiToken($user, 'test-token');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Verify token exists in database
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'test-token',
        ]);
    }

    /**
     * Test that createApiToken uses default token name when not provided.
     */
    public function test_create_api_token_uses_default_name_when_not_provided(): void
    {
        $user = User::factory()->create();

        $token = $this->authenticationService->createApiToken($user);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Verify token exists with default name
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'api-token',
        ]);
    }

    /**
     * Test that revokeAllApiTokens deletes all tokens for a user.
     */
    public function test_revoke_all_api_tokens_deletes_all_tokens_for_user(): void
    {
        $user = User::factory()->create();

        // Create multiple tokens
        $user->createToken('token-1');
        $user->createToken('token-2');
        $user->createToken('token-3');

        $this->assertEquals(3, $user->tokens()->count());

        $this->authenticationService->revokeAllApiTokens($user);

        $this->assertEquals(0, $user->tokens()->count());
    }
}

