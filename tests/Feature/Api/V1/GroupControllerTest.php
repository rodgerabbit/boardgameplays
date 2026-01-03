<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature tests for GroupController API endpoints.
 *
 * These tests verify that the group CRUD API endpoints work correctly,
 * including creation, updates, deletion, rate limiting, and authorization.
 */
class GroupControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Use array cache driver for tests to ensure cache is cleared between tests
        Cache::flush();
    }

    /**
     * Tear down the test environment.
     */
    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    /**
     * Clear all rate limit caches for a user.
     */
    private function clearUserRateLimits(User $user, ?Group $group = null): void
    {
        // Clear all possible rate limit keys
        Cache::forget('group_creation:' . $user->id);
        if ($group !== null) {
            Cache::forget('group_update:' . $user->id . ':' . $group->id);
        }
        // Also clear for any user ID variations
        Cache::flush(); // Nuclear option for tests
    }

    /**
     * Test that the index endpoint returns a list of groups.
     */
    public function test_index_returns_list_of_groups(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        Group::factory()->count(5)->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/groups');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'friendly_name',
                        'description',
                        'group_location',
                        'website_link',
                        'discord_link',
                        'slack_link',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    /**
     * Test that a group can be created successfully.
     */
    public function test_store_creates_group_successfully(): void
    {
        $user = User::factory()->create();

        // Clear any existing rate limit cache
        $this->clearUserRateLimits($user);

        $groupData = [
            'friendly_name' => 'Test Gaming Group',
            'description' => 'A test group',
            'group_location' => 'Test City',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/groups', $groupData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'friendly_name',
                    'description',
                ],
            ]);

        $this->assertDatabaseHas('groups', [
            'friendly_name' => 'Test Gaming Group',
            'created_by_user_id' => $user->id,
        ]);

        // Verify creator is added as admin
        $group = Group::where('friendly_name', 'Test Gaming Group')->first();
        $this->assertNotNull($group);
        $this->assertEquals($user->id, $group->created_by_user_id);
        $this->assertTrue(
            GroupMember::where('group_id', $group->id)
                ->where('user_id', $user->id)
                ->where('role', GroupMember::ROLE_GROUP_ADMIN)
                ->exists()
        );
    }

    /**
     * Test that group creation is rate limited.
     */
    public function test_store_respects_rate_limit(): void
    {
        $user = User::factory()->create();

        // Clear any existing rate limit cache
        $this->clearUserRateLimits($user);

        // Create first group - this will set the rate limit cache
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/groups', [
                'friendly_name' => 'First Group',
            ])
            ->assertStatus(201);

        // Try to create second group immediately (should be rate limited)
        // The middleware will check rate limit and return 429
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/groups', [
                'friendly_name' => 'Second Group',
            ]);

        $response->assertStatus(429);
    }

    /**
     * Test that a group can be retrieved by ID.
     */
    public function test_show_returns_group_details(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $group = Group::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/groups/{$group->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $group->id,
                    'friendly_name' => $group->friendly_name,
                ],
            ]);
    }

    /**
     * Test that only group admins can update a group.
     */
    public function test_update_requires_group_admin(): void
    {
        $adminUser = User::factory()->create();
        $regularUser = User::factory()->create();
        $adminToken = $adminUser->createToken('admin-token')->plainTextToken;
        $regularToken = $regularUser->createToken('regular-token')->plainTextToken;

        $group = Group::factory()->create();
        $groupMember = GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $adminUser->id,
            'role' => GroupMember::ROLE_GROUP_ADMIN,
            'joined_at' => now(),
        ]);

        // Ensure the group member is saved
        $group->refresh();
        $adminUser->refresh();

        // Regular user cannot update
        $response = $this->withHeader('Authorization', "Bearer {$regularToken}")
            ->putJson("/api/v1/groups/{$group->id}", [
                'friendly_name' => 'Updated Name',
            ]);

        $response->assertStatus(403);

        // Clear rate limit cache for admin
        $this->clearUserRateLimits($adminUser, $group);

        // Admin can update - use actingAs to ensure user is loaded correctly
        $response = $this->actingAs($adminUser, 'sanctum')
            ->putJson("/api/v1/groups/{$group->id}", [
                'friendly_name' => 'Updated Name',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'friendly_name' => 'Updated Name',
        ]);
    }

    /**
     * Test that only group admins can delete a group.
     */
    public function test_destroy_requires_group_admin(): void
    {
        $adminUser = User::factory()->create();
        $regularUser = User::factory()->create();
        $adminToken = $adminUser->createToken('admin-token')->plainTextToken;
        $regularToken = $regularUser->createToken('regular-token')->plainTextToken;

        $group = Group::factory()->create();
        $groupMember = GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $adminUser->id,
            'role' => GroupMember::ROLE_GROUP_ADMIN,
            'joined_at' => now(),
        ]);

        // Verify the group member was created
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $adminUser->id,
            'role' => GroupMember::ROLE_GROUP_ADMIN,
        ]);

        // Regular user cannot delete
        $response = $this->withHeader('Authorization', "Bearer {$regularToken}")
            ->deleteJson("/api/v1/groups/{$group->id}");

        $response->assertStatus(403);

        // Verify admin user is actually a group admin before making request
        $this->assertTrue(
            GroupMember::where('group_id', $group->id)
                ->where('user_id', $adminUser->id)
                ->where('role', GroupMember::ROLE_GROUP_ADMIN)
                ->exists(),
            'Admin user should be a group admin'
        );

        // Admin can delete (soft delete) - use actingAs to ensure user is loaded correctly
        $response = $this->actingAs($adminUser, 'sanctum')
            ->deleteJson("/api/v1/groups/{$group->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('groups', [
            'id' => $group->id,
        ]);
    }

    /**
     * Test that unauthenticated requests are rejected.
     */
    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson('/api/v1/groups');
        $response->assertStatus(401);
    }
}
