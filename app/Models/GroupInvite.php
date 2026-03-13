<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupInvite extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'group_invites';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'token',
        'created_by_user_id',
        'expires_at',
        'max_uses',
        'times_used',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the group that owns the invite.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the user who created the invite.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scope a query to only include valid (not expired, under max_uses) invites.
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q): void {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        })->where(function ($q): void {
            $q->whereNull('max_uses')
                ->orWhereRaw('times_used < max_uses');
        });
    }

    /**
     * Check if the invite is still valid.
     */
    public function isValid(): bool
    {
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->times_used >= $this->max_uses) {
            return false;
        }

        return true;
    }
}
