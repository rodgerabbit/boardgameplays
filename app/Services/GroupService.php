<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service class for managing groups.
 *
 * This service handles all business logic related to groups, including
 * creation, updates, deletion, member management, and rate limiting.
 */
class GroupService extends BaseService
{
    /**
     * Create a new group and add the creator as an admin.
     *
     * @param array<string, mixed> $groupData The group data
     * @param User $creator The user creating the group
     * @return Group The created group
     */
    public function createGroup(array $groupData, User $creator): Group
    {
        return DB::transaction(function () use ($groupData, $creator): Group {
            $group = Group::create($groupData);

            // Add creator as group admin
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $creator->id,
                'role' => GroupMember::ROLE_GROUP_ADMIN,
                'joined_at' => now(),
            ]);

            return $group;
        });
    }

    /**
     * Update a group's properties.
     *
     * @param Group $group The group to update
     * @param array<string, mixed> $groupData The updated group data
     * @return Group The updated group
     */
    public function updateGroup(Group $group, array $groupData): Group
    {
        $group->update($groupData);
        return $group->fresh();
    }

    /**
     * Soft delete a group.
     *
     * @param Group $group The group to delete
     * @return bool True if deleted successfully
     */
    public function deleteGroup(Group $group): bool
    {
        return $group->delete();
    }

    /**
     * Restore a soft-deleted group.
     *
     * @param Group $group The group to restore
     * @return bool True if restored successfully
     */
    public function restoreGroup(Group $group): bool
    {
        return $group->restore();
    }

    /**
     * Add a member to a group.
     *
     * @param Group $group The group to add the member to
     * @param User $user The user to add
     * @param string $role The role to assign (default: group_member)
     * @return GroupMember The created group member record
     */
    public function addMemberToGroup(Group $group, User $user, string $role = GroupMember::ROLE_GROUP_MEMBER): GroupMember
    {
        return GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
        ]);
    }

    /**
     * Remove a member from a group.
     *
     * @param Group $group The group to remove the member from
     * @param User $user The user to remove
     * @return bool True if removed successfully
     */
    public function removeMemberFromGroup(Group $group, User $user): bool
    {
        return GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Promote a member to group admin.
     *
     * @param Group $group The group
     * @param User $user The user to promote
     * @return bool True if promoted successfully
     */
    public function promoteMemberToAdmin(Group $group, User $user): bool
    {
        return GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->update(['role' => GroupMember::ROLE_GROUP_ADMIN]);
    }

    /**
     * Demote an admin to regular member.
     *
     * @param Group $group The group
     * @param User $user The user to demote
     * @return bool True if demoted successfully
     */
    public function demoteAdminToMember(Group $group, User $user): bool
    {
        return GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->update(['role' => GroupMember::ROLE_GROUP_MEMBER]);
    }

    /**
     * Check if the user has exceeded the group creation rate limit.
     *
     * @param User $user The user to check
     * @return bool True if rate limit is exceeded
     */
    public function checkCreateRateLimit(User $user): bool
    {
        $key = 'group_creation:' . $user->id;
        $rateLimitSeconds = config('groups.rate_limits.create_seconds', 300);

        if (Cache::has($key)) {
            return true;
        }

        Cache::put($key, true, $rateLimitSeconds);
        return false;
    }

    /**
     * Check if the user has exceeded the group update rate limit.
     *
     * @param User $user The user to check
     * @param Group $group The group being updated
     * @return bool True if rate limit is exceeded
     */
    public function checkUpdateRateLimit(User $user, Group $group): bool
    {
        $key = 'group_update:' . $user->id . ':' . $group->id;
        $rateLimitSeconds = config('groups.rate_limits.update_seconds', 10);

        if (Cache::has($key)) {
            return true;
        }

        Cache::put($key, true, $rateLimitSeconds);
        return false;
    }
}

