<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SyncBoardGamePlaysFromBoardGameGeekJob;
use App\Jobs\SyncUserCollectionFromBoardGameGeekJob;
use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\UserSettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Service class for managing groups.
 *
 * This service handles all business logic related to groups, including
 * creation, updates, deletion, member management, and rate limiting.
 */
class GroupService extends BaseService
{
    public function __construct(
        private readonly GroupAuditLogService $groupAuditLogService
    ) {
    }

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
            $group = Group::create([
                ...$groupData,
                'created_by_user_id' => $creator->id,
            ]);

            // Add creator as group admin
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $creator->id,
                'role' => GroupMember::ROLE_GROUP_ADMIN,
                'joined_at' => now(),
            ]);

            // Set as default group if user doesn't have one yet
            if ($creator->default_group_id === null) {
                $userSettingsService = new UserSettingsService();
                $userSettingsService->setDefaultGroupToFirst($creator);
            }

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
     * @param string|null $displayName Optional display name for the member in this group
     * @return GroupMember The created group member record
     */
    public function addMemberToGroup(Group $group, User $user, string $role = GroupMember::ROLE_GROUP_MEMBER, ?string $displayName = null): GroupMember
    {
        $groupMember = GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $role,
            'display_name' => $displayName,
            'joined_at' => now(),
        ]);

        // Set as default group if user doesn't have one yet
        if ($user->default_group_id === null) {
            $userSettingsService = new UserSettingsService();
            $userSettingsService->setDefaultGroupToFirst($user);
        }

        $this->groupAuditLogService->logMemberJoined($group, $user);

        return $groupMember;
    }

    /**
     * Add a member to a group by BoardGameGeek username. If no user exists with that BGG username,
     * creates a minimal user and triggers async BGG collection and plays sync.
     *
     * @param Group $group The group to add the member to
     * @param string $bggUsername The BoardGameGeek username
     * @param User $addedBy The user adding the member (e.g. group admin)
     * @param string|null $displayName Optional display name for the member in this group
     * @param string $role The role to assign (default: group_member)
     * @return GroupMember The created group member record
     */
    public function addMemberByBggUsername(Group $group, string $bggUsername, User $addedBy, ?string $displayName = null, string $role = GroupMember::ROLE_GROUP_MEMBER): GroupMember
    {
        $bggUsername = trim($bggUsername);
        if ($bggUsername === '') {
            throw new \InvalidArgumentException('BoardGameGeek username cannot be empty.');
        }

        $user = User::where('board_game_geek_username', $bggUsername)->first();

        if ($user === null) {
            $user = $this->createMinimalUserForBggUsername($bggUsername);
            $this->groupAuditLogService->logMemberJoined($group, $user);
            $groupMember = GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => $role,
                'display_name' => $displayName,
                'joined_at' => now(),
            ]);
            if ($user->default_group_id === null) {
                $userSettingsService = new UserSettingsService();
                $userSettingsService->setDefaultGroupToFirst($user);
            }
            SyncUserCollectionFromBoardGameGeekJob::dispatch($user->id)->delay(now()->addSeconds(2));
            SyncBoardGamePlaysFromBoardGameGeekJob::dispatch($user->id, null, null)->delay(now()->addSeconds(4));

            return $groupMember;
        }

        return $this->addMemberToGroup($group, $user, $role, $displayName);
    }

    /**
     * Create a minimal User record for a BGG username (no existing app user).
     * Email is a unique placeholder; password is random. BGG sync is dispatched by caller.
     */
    private function createMinimalUserForBggUsername(string $bggUsername): User
    {
        $domain = config('groups.bgg_invite_placeholder_email_domain', 'boardgameplays.invite');
        $localPart = 'bgg_' . md5(strtolower($bggUsername));
        $email = $localPart . '@' . $domain;

        return User::create([
            'name' => $bggUsername,
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'board_game_geek_username' => $bggUsername,
        ]);
    }

    /**
     * Create an invitation for a group. Returns the GroupInvite with token; URL can be built from token.
     *
     * @param Group $group The group
     * @param User $createdBy The user creating the invite
     * @param \DateTimeInterface|null $expiresAt Optional expiration
     * @param int|null $maxUses Optional max number of uses
     * @return GroupInvite The created invite
     */
    public function createInvite(Group $group, User $createdBy, ?\DateTimeInterface $expiresAt = null, ?int $maxUses = null): GroupInvite
    {
        $token = Str::random(32);

        return GroupInvite::create([
            'group_id' => $group->id,
            'token' => $token,
            'created_by_user_id' => $createdBy->id,
            'expires_at' => $expiresAt,
            'max_uses' => $maxUses,
        ]);
    }

    /**
     * Revoke all valid (non-revoked) invites for a group by setting revoked_at.
     *
     * @param Group $group The group
     * @return int Number of invites revoked
     */
    public function revokeInvitesForGroup(Group $group): int
    {
        return GroupInvite::where('group_id', $group->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * Create a new invite for the group and revoke all existing valid invites (regenerate).
     *
     * @param Group $group The group
     * @param User $createdBy The user regenerating the invite
     * @return GroupInvite The new invite
     */
    public function regenerateInvite(Group $group, User $createdBy): GroupInvite
    {
        $this->revokeInvitesForGroup($group);

        return $this->createInvite($group, $createdBy);
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

