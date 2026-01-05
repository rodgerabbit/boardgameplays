<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Service class for managing user settings.
 *
 * This service handles all business logic related to user settings, including
 * updating default group, theme preference, and play notification delay.
 */
class UserSettingsService extends BaseService
{
    /**
     * Update user settings.
     *
     * @param User $user The user to update settings for
     * @param array<string, mixed> $settingsData The settings data to update
     * @return User The updated user
     */
    public function updateUserSettings(User $user, array $settingsData): User
    {
        return DB::transaction(function () use ($user, $settingsData): User {
            // Validate default_group_id if provided
            if (isset($settingsData['default_group_id'])) {
                $groupId = $settingsData['default_group_id'];
                
                if ($groupId !== null) {
                    // Verify the group exists and user is a member
                    $isMember = $user->groups()->where('groups.id', $groupId)->exists();
                    if (!$isMember) {
                        throw new \InvalidArgumentException(
                            'The specified group does not exist or you are not a member of it.'
                        );
                    }
                }
            }

            // Validate theme_preference if provided
            if (isset($settingsData['theme_preference'])) {
                $validThemes = [
                    User::THEME_LIGHT,
                    User::THEME_DARK,
                    User::THEME_SYSTEM,
                ];
                
                if (!in_array($settingsData['theme_preference'], $validThemes, true)) {
                    throw new \InvalidArgumentException(
                        'Invalid theme preference. Must be one of: ' . implode(', ', $validThemes)
                    );
                }
            }

            // Validate play_notification_delay_hours if provided
            if (isset($settingsData['play_notification_delay_hours'])) {
                $delay = (int) $settingsData['play_notification_delay_hours'];
                
                if ($delay < 0 || $delay > User::MAX_PLAY_NOTIFICATION_DELAY_HOURS) {
                    throw new \InvalidArgumentException(
                        'Play notification delay must be between 0 and ' . User::MAX_PLAY_NOTIFICATION_DELAY_HOURS . ' hours.'
                    );
                }
            }

            $user->update($settingsData);
            
            return $user->fresh();
        });
    }

    /**
     * Set the default group to the first group the user creates or joins.
     *
     * This method will set the user's default_group_id to their first group
     * if they have one and don't already have a default group set.
     *
     * @param User $user The user to set default group for
     * @return User The updated user
     */
    public function setDefaultGroupToFirst(User $user): User
    {
        if ($user->default_group_id !== null) {
            // User already has a default group, verify it's still valid
            $isMember = $user->groups()->where('groups.id', $user->default_group_id)->exists();
            if ($isMember) {
                return $user;
            }
        }

        $firstGroup = $user->getFirstGroup();
        
        if ($firstGroup) {
            $user->update(['default_group_id' => $firstGroup->id]);
        }

        return $user->fresh();
    }

    /**
     * Get the effective default group ID for a user.
     *
     * Returns the user's default_group_id if set and valid, otherwise
     * returns the first group the user creates or joins.
     *
     * @param User $user The user
     * @return int|null The effective default group ID, or null if no groups exist
     */
    public function getEffectiveDefaultGroupId(User $user): ?int
    {
        return $user->getDefaultGroupIdOrFirst();
    }
}
