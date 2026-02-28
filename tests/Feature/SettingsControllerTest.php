<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncBoardGamePlaysFromBoardGameGeekJob;
use App\Jobs\SyncUserCollectionFromBoardGameGeekJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
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
            ->has('user.theme_preference')
            ->has('user.is_profile_public')
            ->has('boardGameGeek')
            ->has('user.board_game_geek_username')
            ->has('theme_preference')  // shared globally by HandleInertiaRequests
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

        // Use a unique writable root so we avoid cleanDirectory() (which can hit "Permission denied"
        // on leftover storage/framework/testing/disks/public from a previous run) and ensure we can create dirs.
        $uniqueRoot = storage_path('framework/testing/disks/public_'.uniqid('', true));
        mkdir($uniqueRoot, 0755, true);
        $originalConfig = config('filesystems.disks.public', []);
        $config = array_merge($originalConfig, ['root' => $uniqueRoot]);
        $fake = $this->app['filesystem']->createLocalDriver($config);
        $this->app['filesystem']->set('public', $fake);

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

    public function test_update_preferences_redirects_guests_to_login(): void
    {
        $response = $this->put('/settings/preferences', [
            'theme_preference' => 'dark',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_update_preferences_updates_theme_preference(): void
    {
        $user = User::factory()->create(['theme_preference' => User::THEME_SYSTEM]);

        $response = $this->actingAs($user)->put('/settings/preferences', [
            'theme_preference' => User::THEME_DARK,
        ]);

        $response->assertRedirect('/settings');
        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertSame(User::THEME_DARK, $user->theme_preference);
    }

    public function test_update_preferences_accepts_light_and_system(): void
    {
        $user = User::factory()->create(['theme_preference' => User::THEME_DARK]);

        $this->actingAs($user)->put('/settings/preferences', [
            'theme_preference' => User::THEME_LIGHT,
        ])->assertRedirect('/settings');
        $user->refresh();
        $this->assertSame(User::THEME_LIGHT, $user->theme_preference);

        $this->actingAs($user)->put('/settings/preferences', [
            'theme_preference' => User::THEME_SYSTEM,
        ])->assertRedirect('/settings');
        $user->refresh();
        $this->assertSame(User::THEME_SYSTEM, $user->theme_preference);
    }

    public function test_update_preferences_validates_theme_preference(): void
    {
        $user = User::factory()->create(['theme_preference' => User::THEME_DARK]);

        $response = $this->actingAs($user)->put('/settings/preferences', [
            'theme_preference' => 'invalid',
        ]);

        $response->assertSessionHasErrors('theme_preference');
        $user->refresh();
        $this->assertSame(User::THEME_DARK, $user->theme_preference);
    }

    public function test_update_preferences_updates_is_profile_public(): void
    {
        $user = User::factory()->create(['is_profile_public' => false]);

        $response = $this->actingAs($user)->put('/settings/preferences', [
            'theme_preference' => $user->theme_preference ?? User::THEME_SYSTEM,
            'is_profile_public' => true,
        ]);

        $response->assertRedirect('/settings');
        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertTrue($user->is_profile_public);
    }

    public function test_update_preferences_can_set_is_profile_public_to_false(): void
    {
        $user = User::factory()->create(['is_profile_public' => true]);

        $response = $this->actingAs($user)->put('/settings/preferences', [
            'theme_preference' => $user->theme_preference ?? User::THEME_SYSTEM,
            'is_profile_public' => false,
        ]);

        $response->assertRedirect('/settings');
        $user->refresh();
        $this->assertFalse($user->is_profile_public);
    }

    public function test_update_boardgamegeek_saves_username_and_dispatches_sync_jobs(): void
    {
        Queue::fake();
        $user = User::factory()->create(['board_game_geek_username' => null]);

        $response = $this->actingAs($user)->put('/settings/boardgamegeek', [
            'board_game_geek_username' => 'bgguser',
            'sync_plays_to_board_game_geek' => false,
            'use_generic_user_for_bgg_plays' => true,
        ]);

        $response->assertRedirect('/settings');
        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertSame('bgguser', $user->board_game_geek_username);

        Queue::assertPushed(SyncUserCollectionFromBoardGameGeekJob::class);
        Queue::assertPushed(SyncBoardGamePlaysFromBoardGameGeekJob::class);
    }

    public function test_update_boardgamegeek_rejects_duplicate_username(): void
    {
        $existing = User::factory()->create(['board_game_geek_username' => 'taken']);
        $user = User::factory()->create(['board_game_geek_username' => null]);

        $response = $this->actingAs($user)->put('/settings/boardgamegeek', [
            'board_game_geek_username' => 'taken',
            'sync_plays_to_board_game_geek' => false,
            'use_generic_user_for_bgg_plays' => true,
        ]);

        $response->assertSessionHasErrors('board_game_geek_username');
        $user->refresh();
        $this->assertNull($user->board_game_geek_username);
    }

    public function test_update_boardgamegeek_updates_toggles_without_changing_username(): void
    {
        Queue::fake();
        $user = User::factory()->create([
            'board_game_geek_username' => 'mybgg',
            'sync_plays_to_board_game_geek' => false,
            'use_generic_user_for_bgg_plays' => true,
        ]);

        $response = $this->actingAs($user)->put('/settings/boardgamegeek', [
            'board_game_geek_username' => 'mybgg',
            'sync_plays_to_board_game_geek' => true,
            'use_generic_user_for_bgg_plays' => true,
        ]);

        $response->assertRedirect('/settings');
        $user->refresh();
        $this->assertTrue($user->sync_plays_to_board_game_geek);
        $this->assertSame('mybgg', $user->board_game_geek_username);
        // Username unchanged so no sync jobs dispatched
        Queue::assertNotPushed(SyncUserCollectionFromBoardGameGeekJob::class);
    }

    public function test_request_manual_boardgamegeek_sync_requires_username(): void
    {
        $user = User::factory()->create(['board_game_geek_username' => null]);

        $response = $this->actingAs($user)->post('/settings/boardgamegeek/sync');

        $response->assertRedirect('/settings');
        $response->assertSessionHas('error');
    }

    public function test_request_manual_boardgamegeek_sync_allowed_when_cooldown_passed(): void
    {
        Queue::fake();
        $user = User::factory()->create([
            'board_game_geek_username' => 'bgguser',
            'bgg_manual_sync_requested_at' => now()->subHours(25),
        ]);

        $response = $this->actingAs($user)->post('/settings/boardgamegeek/sync');

        $response->assertRedirect('/settings');
        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertNotNull($user->bgg_manual_sync_requested_at);
        Queue::assertPushed(SyncUserCollectionFromBoardGameGeekJob::class);
        Queue::assertPushed(SyncBoardGamePlaysFromBoardGameGeekJob::class);
    }

    public function test_request_manual_boardgamegeek_sync_denied_within_24_hours(): void
    {
        Queue::fake();
        $user = User::factory()->create([
            'board_game_geek_username' => 'bgguser',
            'bgg_manual_sync_requested_at' => now()->subHours(1),
        ]);

        $response = $this->actingAs($user)->post('/settings/boardgamegeek/sync');

        $response->assertRedirect('/settings');
        $response->assertSessionHas('error');
        Queue::assertNotPushed(SyncUserCollectionFromBoardGameGeekJob::class);
    }
}
