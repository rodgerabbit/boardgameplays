<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Group;
use App\Models\GroupAuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service class for managing group audit logs.
 *
 * This service handles all business logic related to audit logging
 * for group actions, including creation, updates, deletions, and member changes.
 */
class GroupAuditLogService extends BaseService
{
    /**
     * Log a group action.
     *
     * @param Group $group The group the action is performed on
     * @param string $action The action being performed
     * @param User|null $user The user performing the action (nullable for system actions)
     * @param array<string, mixed>|null $changes The before/after changes (for updates)
     * @param array<string, mixed>|null $metadata Additional context metadata
     * @return GroupAuditLog The created audit log entry
     */
    public function logGroupAction(
        Group $group,
        string $action,
        ?User $user = null,
        ?array $changes = null,
        ?array $metadata = null
    ): GroupAuditLog {
        return GroupAuditLog::create([
            'group_id' => $group->id,
            'user_id' => $user?->id,
            'action' => $action,
            'changes' => $changes,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log a group creation action.
     *
     * @param Group $group The created group
     * @param User $user The user who created the group
     * @return GroupAuditLog The created audit log entry
     */
    public function logGroupCreated(Group $group, User $user): GroupAuditLog
    {
        return $this->logGroupAction(
            group: $group,
            action: GroupAuditLog::ACTION_CREATED,
            user: $user,
            metadata: [
                'friendly_name' => $group->friendly_name,
            ]
        );
    }

    /**
     * Log a group update action.
     *
     * @param Group $group The updated group
     * @param User $user The user who updated the group
     * @param array<string, mixed> $changes The before/after changes
     * @return GroupAuditLog The created audit log entry
     */
    public function logGroupUpdated(Group $group, User $user, array $changes): GroupAuditLog
    {
        return $this->logGroupAction(
            group: $group,
            action: GroupAuditLog::ACTION_UPDATED,
            user: $user,
            changes: $changes
        );
    }

    /**
     * Log a group deletion action.
     *
     * @param Group $group The deleted group
     * @param User $user The user who deleted the group
     * @return GroupAuditLog The created audit log entry
     */
    public function logGroupDeleted(Group $group, User $user): GroupAuditLog
    {
        return $this->logGroupAction(
            group: $group,
            action: GroupAuditLog::ACTION_DELETED,
            user: $user,
            metadata: [
                'friendly_name' => $group->friendly_name,
            ]
        );
    }

    /**
     * Log a group restoration action.
     *
     * @param Group $group The restored group
     * @param User $user The user who restored the group
     * @return GroupAuditLog The created audit log entry
     */
    public function logGroupRestored(Group $group, User $user): GroupAuditLog
    {
        return $this->logGroupAction(
            group: $group,
            action: GroupAuditLog::ACTION_RESTORED,
            user: $user,
            metadata: [
                'friendly_name' => $group->friendly_name,
            ]
        );
    }

    /**
     * Log a member joining action.
     *
     * @param Group $group The group
     * @param User $user The user who joined
     * @return GroupAuditLog The created audit log entry
     */
    public function logMemberJoined(Group $group, User $user): GroupAuditLog
    {
        return $this->logGroupAction(
            group: $group,
            action: GroupAuditLog::ACTION_MEMBER_JOINED,
            user: $user,
            metadata: [
                'member_name' => $user->name,
                'member_email' => $user->email,
            ]
        );
    }

    /**
     * Log a member leaving action.
     *
     * @param Group $group The group
     * @param User $user The user who left
     * @return GroupAuditLog The created audit log entry
     */
    public function logMemberLeft(Group $group, User $user): GroupAuditLog
    {
        return $this->logGroupAction(
            group: $group,
            action: GroupAuditLog::ACTION_MEMBER_LEFT,
            user: $user,
            metadata: [
                'member_name' => $user->name,
                'member_email' => $user->email,
            ]
        );
    }

    /**
     * Log a member promotion action.
     *
     * @param Group $group The group
     * @param User $promotedUser The user who was promoted
     * @param User $promoterUser The user who performed the promotion
     * @return GroupAuditLog The created audit log entry
     */
    public function logMemberPromoted(Group $group, User $promotedUser, User $promoterUser): GroupAuditLog
    {
        return $this->logGroupAction(
            group: $group,
            action: GroupAuditLog::ACTION_MEMBER_PROMOTED,
            user: $promoterUser,
            metadata: [
                'promoted_user_id' => $promotedUser->id,
                'promoted_user_name' => $promotedUser->name,
            ]
        );
    }

    /**
     * Log a member demotion action.
     *
     * @param Group $group The group
     * @param User $demotedUser The user who was demoted
     * @param User $demoterUser The user who performed the demotion
     * @return GroupAuditLog The created audit log entry
     */
    public function logMemberDemoted(Group $group, User $demotedUser, User $demoterUser): GroupAuditLog
    {
        return $this->logGroupAction(
            group: $group,
            action: GroupAuditLog::ACTION_MEMBER_DEMOTED,
            user: $demoterUser,
            metadata: [
                'demoted_user_id' => $demotedUser->id,
                'demoted_user_name' => $demotedUser->name,
            ]
        );
    }

    /**
     * Get audit logs for a group.
     *
     * @param Group $group The group
     * @param int $perPage Number of items per page (for pagination)
     * @param array<string>|null $actions Filter by specific actions
     * @return LengthAwarePaginator|Collection The audit logs
     */
    public function getGroupAuditLogs(Group $group, int $perPage = 15, ?array $actions = null): LengthAwarePaginator|Collection
    {
        $query = GroupAuditLog::where('group_id', $group->id)
            ->with('user')
            ->orderBy('created_at', 'desc');

        if ($actions !== null && count($actions) > 0) {
            $query->whereIn('action', $actions);
        }

        if ($perPage > 0) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }
}

