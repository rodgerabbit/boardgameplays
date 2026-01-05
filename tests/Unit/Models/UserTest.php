<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for User model.
 *
 * These tests verify the User model's methods and relationships,
 * particularly the settings-related methods.
 */
class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that getFirstGroup returns the first group user creates.
     */
    public function test_get_first_group_returns_first_created_group(): void
    {
        $user = User::factory()->create();

        $firstGroup = Group::factory()->create([
            'created_by_user_id' => $user->id,
            'created_at' => now()->subDays(2),
        ]);

        $secondGroup = Group::factory()->create([
            'created_by_user_id' => $user->id,
            'created_at' => now()->subDays(1),
        ]);

        $result = $user->getFirstGroup();

        $this->assertNotNull($result);
        $this->assertEquals($firstGroup->id, $result->id);
    }

    /**
     * Test that getFirstGroup returns the first group user joins.
     */
    public function test_get_first_group_returns_first_joined_group(): void
    {
        $user = User::factory()->create();

        $firstGroup = Group::factory()->create();
        GroupMember::factory()->create([
            'group_id' => $firstGroup->id,
            'user_id' => $user->id,
            'joined_at' => now()->subDays(2),
        ]);

        $secondGroup = Group::factory()->create();
        GroupMember::factory()->create([
            'group_id' => $secondGroup->id,
            'user_id' => $user->id,
            'joined_at' => now()->subDays(1),
        ]);

        $result = $user->getFirstGroup();

        $this->assertNotNull($result);
        $this->assertEquals($firstGroup->id, $result->id);
    }

    /**
     * Test that getFirstGroup returns the earliest between created and joined groups.
     */
    public function test_get_first_group_returns_earliest_between_created_and_joined(): void
    {
        $user = User::factory()->create();

        // User creates a group 2 days ago
        $createdGroup = Group::factory()->create([
            'created_by_user_id' => $user->id,
            'created_at' => now()->subDays(2),
        ]);

        // User joins a group 3 days ago (earlier)
        $joinedGroup = Group::factory()->create();
        GroupMember::factory()->create([
            'group_id' => $joinedGroup->id,
            'user_id' => $user->id,
            'joined_at' => now()->subDays(3),
        ]);

        $result = $user->getFirstGroup();

        $this->assertNotNull($result);
        $this->assertEquals($joinedGroup->id, $result->id);
    }

    /**
     * Test that getFirstGroup returns null when user has no groups.
     */
    public function test_get_first_group_returns_null_when_user_has_no_groups(): void
    {
        $user = User::factory()->create();

        $result = $user->getFirstGroup();

        $this->assertNull($result);
    }

    /**
     * Test that getDefaultGroupIdOrFirst returns default group ID when set and valid.
     */
    public function test_get_default_group_id_or_first_returns_default_when_set_and_valid(): void
    {
        $user = User::factory()->create();
        $defaultGroup = Group::factory()->create();
        GroupMember::factory()->create([
            'group_id' => $defaultGroup->id,
            'user_id' => $user->id,
        ]);

        $user->update(['default_group_id' => $defaultGroup->id]);

        $result = $user->getDefaultGroupIdOrFirst();

        $this->assertEquals($defaultGroup->id, $result);
    }

    /**
     * Test that getDefaultGroupIdOrFirst returns first group when default is not set.
     */
    public function test_get_default_group_id_or_first_returns_first_when_default_not_set(): void
    {
        $user = User::factory()->create();
        $firstGroup = Group::factory()->create();
        GroupMember::factory()->create([
            'group_id' => $firstGroup->id,
            'user_id' => $user->id,
        ]);

        $result = $user->getDefaultGroupIdOrFirst();

        $this->assertEquals($firstGroup->id, $result);
    }

    /**
     * Test that getDefaultGroupIdOrFirst returns first group when default is invalid.
     */
    public function test_get_default_group_id_or_first_returns_first_when_default_invalid(): void
    {
        $user = User::factory()->create();
        $invalidGroup = Group::factory()->create();
        $validGroup = Group::factory()->create();
        
        // User has default_group_id set to a group they're not a member of
        $user->update(['default_group_id' => $invalidGroup->id]);
        
        // User is a member of validGroup
        GroupMember::factory()->create([
            'group_id' => $validGroup->id,
            'user_id' => $user->id,
        ]);

        $result = $user->getDefaultGroupIdOrFirst();

        $this->assertEquals($validGroup->id, $result);
    }

    /**
     * Test that getDefaultGroupIdOrFirst returns null when user has no groups.
     */
    public function test_get_default_group_id_or_first_returns_null_when_no_groups(): void
    {
        $user = User::factory()->create();

        $result = $user->getDefaultGroupIdOrFirst();

        $this->assertNull($result);
    }

    /**
     * Test that defaultGroup relationship works correctly.
     */
    public function test_default_group_relationship_works_correctly(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();
        $user->update(['default_group_id' => $group->id]);

        $this->assertNotNull($user->defaultGroup);
        $this->assertEquals($group->id, $user->defaultGroup->id);
    }
}
