<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    /**
     * Determine whether the user can view any models.
     * Any authenticated user can view groups.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Any authenticated user can view a group.
     */
    public function view(User $user, Group $group): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     * Any authenticated user can create groups (rate limit enforced separately).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     * Only group admins can update.
     */
    public function update(User $user, Group $group): bool
    {
        return $user->isGroupAdmin($group->id);
    }

    /**
     * Determine whether the user can delete the model.
     * Only group admins can delete.
     */
    public function delete(User $user, Group $group): bool
    {
        // Ensure user has an ID
        if (!$user->id) {
            return false;
        }
        
        return $user->isGroupAdmin($group->id);
    }

    /**
     * Determine whether the user can restore the model.
     * Only system admins can restore.
     */
    public function restore(User $user, Group $group): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view audit logs.
     * Group admins and system admins can view audit logs.
     */
    public function viewAuditLogs(User $user, Group $group): bool
    {
        return $user->isAdmin() || $user->isGroupAdmin($group->id);
    }
}
