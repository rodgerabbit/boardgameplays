<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for UserSettingsController API endpoints.
 *
 * These tests verify that the user settings API endpoints work correctly,
 * including retrieving and updating default group, theme preference, and
 * play notification delay settings.
 */
class UserSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the show endpoint returns user settings.
     */
    public function test_show_returns_user_settings(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'default_group_id',
                    'effective_default_group_id',
                    'theme_preference',
                    'play_notification_delay_hours',
                ],
            ])
            ->assertJson([
                'data' => [
                    'default_group_id' => null,
                    'theme_preference' => 'system',
                    'play_notification_delay_hours' => 0,
                ],
            ]);
    }

    /**
     * Test that show returns effective default group when no default is set.
     */
    public function test_show_returns_effective_default_group_when_no_default_set(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Create a group and add user as member
        $group = Group::factory()->create(['created_by_user_id' => $user->id]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => GroupMember::ROLE_GROUP_ADMIN,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user/settings');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'default_group_id' => null,
                    'effective_default_group_id' => $group->id,
                ],
            ]);
    }

    /**
     * Test that show returns null effective default group when user has no groups.
     */
    public function test_show_returns_null_effective_default_group_when_user_has_no_groups(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/user/settings');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'default_group_id' => null,
                    'effective_default_group_id' => null,
                ],
            ]);
    }

    /**
     * Test that update can set default group.
     */
    public function test_update_can_set_default_group(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $group = Group::factory()->create();
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/user/settings', [
                'default_group_id' => $group->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'default_group_id' => $group->id,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'default_group_id' => $group->id,
        ]);
    }

    /**
     * Test that update cannot set default group to a group user is not a member of.
     */
    public function test_update_cannot_set_default_group_to_non_member_group(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $group = Group::factory()->create();
        // User is not a member of this group

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/user/settings', [
                'default_group_id' => $group->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['default_group_id']);
    }

    /**
     * Test that update can clear default group.
     */
    public function test_update_can_clear_default_group(): void
    {
        $user = User::factory()->create([
            'default_group_id' => Group::factory()->create()->id,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/user/settings', [
                'default_group_id' => null,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'default_group_id' => null,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'default_group_id' => null,
        ]);
    }

    /**
     * Test that update can set theme preference to light.
     */
    public function test_update_can_set_theme_preference_to_light(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/user/settings', [
                'theme_preference' => 'light',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'theme_preference' => 'light',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'theme_preference' => 'light',
        ]);
    }

    /**
     * Test that update can set theme preference to dark.
     */
    public function test_update_can_set_theme_preference_to_dark(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/user/settings', [
                'theme_preference' => 'dark',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'theme_preference' => 'dark',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'theme_preference' => 'dark',
        ]);
    }

    /**
     * Test that update can set theme preference to system.
     */
    public function test_update_can_set_theme_preference_to_system(): void
    {
        $user = User::factory()->create(['theme_preference' => 'dark']);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/user/settings', [
                'theme_preference' => 'system',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'theme_preference' => 'system',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'theme_preference' => 'system',
        ]);
    }

    /**
     * Test that update rejects invalid theme preference.
     */
    public function test_update_rejects_invalid_theme_preference(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/user/settings', [
                'theme_preference' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['theme_preference']);
    }

    /**
     * Test that update can set play notification delay to 0 hours.
     */
    public function test_update_can_set_play_notification_delay_to_zero(): void
    {
        $user = User::factory()->create(['play_notification_delay_hours' => 2]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/user/settings', [
                'play_notification_delay_hours' => 0,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'play_notification_delay_hours' => 0,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'play_notification_delay_hours' => 0,
        ]);
    }

    /**
     * Test that update can set play notification delay to maximum (4 hours).
     */
    public function test_update_can_set_play_notification_delay_to_maximum(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/user/settings', [
                'play_notification_delay_hours' => 4,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'play_notification_delay_hours' => 4,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'play_notification_delay_hours' => 4,
        ]);
    }

    /**
     * Test that update rejects play notification delay below minimum (negative).
     */
    public function test_update_rejects_play_notification_delay_below_minimum(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/user/settings', [
                'play_notification_delay_hours' => -1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['play_notification_delay_hours']);
    }

    /**
     * Test that update rejects play notification delay above maximum.
     */
    public function test_update_rejects_play_notification_delay_above_maximum(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/user/settings', [
                'play_notification_delay_hours' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['play_notification_delay_hours']);
    }

    /**
     * Test that update can update multiple settings at once.
     */
    public function test_update_can_update_multiple_settings_at_once(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $group = Group::factory()->create();
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/user/settings', [
                'default_group_id' => $group->id,
                'theme_preference' => 'dark',
                'play_notification_delay_hours' => 2,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'default_group_id' => $group->id,
                    'theme_preference' => 'dark',
                    'play_notification_delay_hours' => 2,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'default_group_id' => $group->id,
            'theme_preference' => 'dark',
            'play_notification_delay_hours' => 2,
        ]);
    }

    /**
     * Test that update requires authentication.
     */
    public function test_update_requires_authentication(): void
    {
        $response = $this->patchJson('/api/v1/user/settings', [
            'theme_preference' => 'dark',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test that show requires authentication.
     */
    public function test_show_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/user/settings');

        $response->assertStatus(401);
    }

    /**
     * Test that update only updates provided fields.
     */
    public function test_update_only_updates_provided_fields(): void
    {
        $user = User::factory()->create([
            'theme_preference' => 'light',
            'play_notification_delay_hours' => 1,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/user/settings', [
                'theme_preference' => 'dark',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'theme_preference' => 'dark',
            'play_notification_delay_hours' => 1, // Should remain unchanged
        ]);
    }
}
