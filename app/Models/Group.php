<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Group extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Visibility constants.
     */
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_VIEWABLE = 'viewable';
    public const VISIBILITY_PUBLICLY_JOINABLE = 'publicly_joinable';

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
        'photo_path',
        'visibility',
        'created_by_user_id',
    ];

    /**
     * Get the attributes that should be appended to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
        ];
    }

    /**
     * Get the user who created the group.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

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
     * Scope a query to add last_active_at as a computed column (max of updated_at, latest audit log, latest play).
     */
    public function scopeWithLastActiveAt($query)
    {
        return $query->selectRaw(
            'groups.*, GREATEST(' .
            'groups.updated_at, ' .
            'COALESCE((SELECT MAX(created_at) FROM group_audit_logs WHERE group_audit_logs.group_id = groups.id), groups.updated_at), ' .
            'COALESCE((SELECT MAX(played_at) FROM board_game_plays WHERE board_game_plays.group_id = groups.id), groups.updated_at)' .
            ') as last_active_at'
        );
    }

    /**
     * Get the member count attribute.
     */
    public function getMemberCountAttribute(): int
    {
        return $this->groupMembers()->count();
    }

    /**
     * Get the URL for the group's photo.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->photo_path) {
                return null;
            }

            return Storage::disk('public')->url($this->photo_path);
        });
    }

    /**
     * Get the group invites for the group.
     */
    public function groupInvites(): HasMany
    {
        return $this->hasMany(GroupInvite::class);
    }
}
