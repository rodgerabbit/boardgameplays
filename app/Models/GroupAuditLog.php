<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupAuditLog extends Model
{
    use HasFactory;

    /**
     * Action constants.
     */
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_RESTORED = 'restored';
    public const ACTION_MEMBER_JOINED = 'member_joined';
    public const ACTION_MEMBER_LEFT = 'member_left';
    public const ACTION_MEMBER_PROMOTED = 'member_promoted';
    public const ACTION_MEMBER_DEMOTED = 'member_demoted';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'group_audit_logs';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'user_id',
        'action',
        'changes',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the group that owns the audit log.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the user that performed the action (nullable for system actions).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
