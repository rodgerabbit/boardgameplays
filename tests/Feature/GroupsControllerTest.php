<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for web GroupsController (group detail page).
 */
class GroupsControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a group member can view the group show page.
     */
    public function test_show_returns_200_for_member(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => GroupMember::ROLE_GROUP_MEMBER,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('groups.show', ['id' => $group->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Groups/Show')
            ->has('group')
            ->where('group.id', $group->id)
            ->has('currentUserRole')
            ->has('groupSummary'));
    }

    /**
     * Test that a non-member receives 403 when viewing the group show page.
     */
    public function test_show_returns_403_for_non_member(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('groups.show', ['id' => $group->id]));

        $response->assertStatus(403);
    }
}
