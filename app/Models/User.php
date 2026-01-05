<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Theme preference constants.
     */
    public const THEME_LIGHT = 'light';
    public const THEME_DARK = 'dark';
    public const THEME_SYSTEM = 'system';

    /**
     * Maximum play notification delay in hours.
     */
    public const MAX_PLAY_NOTIFICATION_DELAY_HOURS = 4;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'default_group_id',
        'theme_preference',
        'play_notification_delay_hours',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'play_notification_delay_hours' => 'integer',
        ];
    }

    /**
     * Get the groups that the user belongs to.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get the group memberships for the user.
     */
    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Get the roles that the user has.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Get the audit logs created by the user.
     */
    public function groupAuditLogs(): HasMany
    {
        return $this->hasMany(GroupAuditLog::class);
    }

    /**
     * Get the groups created by the user.
     */
    public function createdGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'created_by_user_id');
    }

    /**
     * Get the default group for the user.
     */
    public function defaultGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'default_group_id');
    }

    /**
     * Check if the user is a system admin.
     */
    public function isAdmin(): bool
    {
        return $this->roles()->where('name', Role::ROLE_ADMIN)->exists();
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if the user is a group admin for a specific group.
     */
    public function isGroupAdmin(int $groupId): bool
    {
        if (!$this->id) {
            return false;
        }
        
        return GroupMember::where('group_id', $groupId)
            ->where('user_id', $this->id)
            ->where('role', GroupMember::ROLE_GROUP_ADMIN)
            ->exists();
    }

    /**
     * Get the first group the user creates or joins.
     *
     * This method returns the first group the user is a member of, based on
     * the earliest joined_at timestamp from group_members, or the earliest
     * created group if the user created it.
     *
     * @return Group|null The first group, or null if the user has no groups
     */
    public function getFirstGroup(): ?Group
    {
        if (!$this->id) {
            return null;
        }

        // Get the first group the user joined (by joined_at)
        $firstJoinedGroup = $this->groups()
            ->orderBy('group_members.joined_at', 'asc')
            ->first();

        // Get the first group the user created
        $firstCreatedGroup = $this->createdGroups()
            ->orderBy('created_at', 'asc')
            ->first();

        // Return the earliest one
        if ($firstJoinedGroup && $firstCreatedGroup) {
            $joinedAt = $this->groupMemberships()
                ->where('group_id', $firstJoinedGroup->id)
                ->value('joined_at');
            
            if ($joinedAt && $joinedAt->lt($firstCreatedGroup->created_at)) {
                return $firstJoinedGroup;
            }
            return $firstCreatedGroup;
        }

        return $firstJoinedGroup ?? $firstCreatedGroup;
    }

    /**
     * Get the default group ID, or the first group if no default is set.
     *
     * @return int|null The default group ID, or null if no groups exist
     */
    public function getDefaultGroupIdOrFirst(): ?int
    {
        if ($this->default_group_id) {
            // Verify the default group still exists and user is a member
            $isMember = $this->groups()->where('groups.id', $this->default_group_id)->exists();
            if ($isMember) {
                return $this->default_group_id;
            }
        }

        // Fall back to first group
        $firstGroup = $this->getFirstGroup();
        return $firstGroup?->id;
    }
}
