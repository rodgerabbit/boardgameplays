<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BoardGamePlay;
use App\Models\User;

class BoardGamePlayPolicy
{
    /**
     * Determine whether the user can view any models.
     * Any authenticated user can view plays.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Users can view plays in their groups or their own plays.
     */
    public function view(User $user, BoardGamePlay $boardGamePlay): bool
    {
        // Users can view their own plays
        if ($boardGamePlay->created_by_user_id === $user->id) {
            return true;
        }

        // Users can view plays in groups they belong to
        if ($boardGamePlay->group_id !== null) {
            return $user->groups()->where('groups.id', $boardGamePlay->group_id)->exists();
        }

        // Users cannot view plays that are not in a group and not their own
        return false;
    }

    /**
     * Determine whether the user can create models.
     * Any authenticated user can create plays.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     * Users can update their own plays or group admin for group plays.
     */
    public function update(User $user, BoardGamePlay $boardGamePlay): bool
    {
        // Users can update their own plays
        if ($boardGamePlay->created_by_user_id === $user->id) {
            return true;
        }

        // Group admins can update plays in their groups
        if ($boardGamePlay->group_id !== null) {
            return $user->isGroupAdmin($boardGamePlay->group_id);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * Users can delete their own plays or group admin for group plays.
     */
    public function delete(User $user, BoardGamePlay $boardGamePlay): bool
    {
        // Users can delete their own plays
        if ($boardGamePlay->created_by_user_id === $user->id) {
            return true;
        }

        // Group admins can delete plays in their groups
        if ($boardGamePlay->group_id !== null) {
            return $user->isGroupAdmin($boardGamePlay->group_id);
        }

        return false;
    }
}

