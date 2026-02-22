<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for SettingsController (web settings page and profile update).
 *
 * Run tests with Docker Compose: docker compose exec app php artisan test tests/Feature/SettingsControllerTest.php
 */
class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_settings_page_redirects_guests_to_login(): void
    {
        $response = $this->get('/settings');

        $response->assertRedirect(route('login'));
    }

    public function test_settings_page_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'biography' => 'Board game enthusiast.',
        ]);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Settings', false)  // skip file existence check (path is js/pages, not js/Pages)
            ->has('user')
            ->where('user.id', $user->id)
            ->where('user.name', 'Jane Doe')
            ->where('user.email', 'jane@example.com')
            ->where('user.biography', 'Board game enthusiast.')
        );
    }

    public function test_update_profile_redirects_guests_to_login(): void
    {
        $response = $this->post('/settings/profile', [
            'name' => 'New Name',
            'biography' => 'New bio',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_update_profile_updates_name_and_biography(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'biography' => 'Old bio',
        ]);

        $response = $this->actingAs($user)->post('/settings/profile', [
            'name' => 'Updated Name',
            'biography' => 'Updated biography.',
        ]);

        $response->assertRedirect('/settings');
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('Updated biography.', $user->biography);
    }

    public function test_update_profile_validates_name_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/settings/profile', [
            'name' => '',
            'biography' => 'Some bio',
        ]);

        $response->assertSessionHasErrors('name');
        $user->refresh();
        $this->assertNotSame('', $user->name);
    }

    public function test_update_profile_accepts_optional_biography(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'biography' => 'Has a bio',
        ]);

        $response = $this->actingAs($user)->post('/settings/profile', [
            'name' => 'Test User',
            'biography' => '',
        ]);

        $response->assertRedirect('/settings');
        $user->refresh();
        $this->assertNull($user->biography);
    }

    /**
     * Profile picture upload is tested only when GD (imagejpeg) is available (e.g. in Docker app container).
     */
    public function test_update_profile_stores_profile_picture_when_provided(): void
    {
        if (! function_exists('imagejpeg')) {
            $this->markTestSkipped('GD extension required for image upload test');
        }

        Storage::fake('public');
        $user = User::factory()->create(['name' => 'Photo User']);
        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $response = $this->actingAs($user)->post('/settings/profile', [
            'name' => 'Photo User',
            'biography' => null,
            'profile_picture' => $file,
        ]);

        $response->assertRedirect('/settings');
        $user->refresh();
        $this->assertNotNull($user->profile_picture_path);
        Storage::disk('public')->assertExists($user->profile_picture_path);
    }
}
