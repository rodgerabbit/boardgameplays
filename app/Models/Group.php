<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'friendly_name',
        'description',
        'group_location',
        'website_link',
        'discord_link',
        'slack_link',
    ];

    /**
     * Get the group members for the group.
     */
    public function groupMembers(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Get the members (users) for the group through group_members.
     */
    public function members(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, GroupMember::class, 'group_id', 'id', 'id', 'user_id');
    }

    /**
     * Get the audit logs for the group.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(GroupAuditLog::class);
    }

    /**
     * Get the group admins (members with group_admin role).
     */
    public function groupAdmins(): HasMany
    {
        return $this->hasMany(GroupMember::class)->where('role', GroupMember::ROLE_GROUP_ADMIN);
    }

    /**
     * Scope a query to include members.
     */
    public function scopeWithMembers($query)
    {
        return $query->with('groupMembers.user');
    }

    /**
     * Scope a query to include audit logs.
     */
    public function scopeWithAuditLogs($query)
    {
        return $query->with('auditLogs.user');
    }

    /**
     * Get the member count attribute.
     */
    public function getMemberCountAttribute(): int
    {
        return $this->groupMembers()->count();
    }
}
