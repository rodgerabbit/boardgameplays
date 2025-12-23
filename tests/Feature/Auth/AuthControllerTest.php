<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature tests for AuthController (web authentication).
 *
 * These tests verify that the web-based authentication endpoints work correctly,
 * including login, registration, and logout using session-based authentication.
 */
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     *
     * Disables CSRF middleware for web route tests to avoid 419 errors.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Disable CSRF middleware for web route tests
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    /**
     * Test that login form is accessible to guests.
     */
    public function test_login_form_is_accessible_to_guests(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    /**
     * Test that login form redirects authenticated users.
     */
    public function test_login_form_redirects_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect('/');
    }

    /**
     * Test that registration form is accessible to guests.
     */
    public function test_register_form_is_accessible_to_guests(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    /**
     * Test that registration form redirects authenticated users.
     */
    public function test_register_form_redirects_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/register');

        $response->assertRedirect('/');
    }

    /**
     * Test that login succeeds with correct credentials.
     */
    public function test_login_succeeds_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Test that login fails with incorrect credentials.
     */
    public function test_login_fails_with_incorrect_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    /**
     * Test that login validates required fields.
     */
    public function test_login_validates_required_fields(): void
    {
        $response = $this->post('/login', []);

        $response->assertSessionHasErrors(['email', 'password']);
    }

    /**
     * Test that login validates email format.
     */
    public function test_login_validates_email_format(): void
    {
        $response = $this->post('/login', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /**
     * Test that registration creates a new user and logs them in.
     */
    public function test_register_creates_user_and_logs_them_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    /**
     * Test that registration validates required fields.
     */
    public function test_register_validates_required_fields(): void
    {
        $response = $this->post('/register', []);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
    }

    /**
     * Test that registration validates email uniqueness.
     */
    public function test_register_validates_email_uniqueness(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /**
     * Test that registration validates password confirmation.
     */
    public function test_register_validates_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    /**
     * Test that logout logs out the authenticated user.
     */
    public function test_logout_logs_out_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /**
     * Test that logout requires authentication.
     */
    public function test_logout_requires_authentication(): void
    {
        $response = $this->post('/logout');

        $response->assertRedirect('/login');
    }
}

