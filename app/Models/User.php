<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
}
