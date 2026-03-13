<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\GroupAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for cross-group activity (audit log entry with group context).
 *
 * Transforms a GroupAuditLog into a consistent JSON structure for the activity feed.
 */
class GroupActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $log = $this->resource;
        $user = $this->whenLoaded('user');
        $group = $this->whenLoaded('group');

        return [
            'id' => $log->id,
            'action' => $log->action,
            'description' => $this->getActivityDescription($log),
            'changes' => $log->changes,
            'metadata' => $log->metadata,
            'user' => new UserResource($user),
            'group' => $group ? [
                'id' => $group->id,
                'friendly_name' => $group->friendly_name,
            ] : null,
            'created_at' => $log->created_at->toIso8601String(),
        ];
    }

    /**
     * Get a human-readable description for the activity (e.g. "Sarah Chen logged a play in Board Game Knights").
     */
    private function getActivityDescription(GroupAuditLog $log): string
    {
        $userName = $log->user?->name ?? 'Someone';
        $groupName = $log->group->friendly_name ?? 'a group';

        return match ($log->action) {
            GroupAuditLog::ACTION_CREATED => "{$userName} created the group",
            GroupAuditLog::ACTION_UPDATED => "{$userName} updated the group",
            GroupAuditLog::ACTION_MEMBER_JOINED => "{$userName} joined {$groupName}",
            GroupAuditLog::ACTION_MEMBER_LEFT => "{$userName} left {$groupName}",
            GroupAuditLog::ACTION_MEMBER_PROMOTED => "{$userName} was promoted to admin in {$groupName}",
            GroupAuditLog::ACTION_MEMBER_DEMOTED => "{$userName} was demoted in {$groupName}",
            GroupAuditLog::ACTION_PLAY_LOGGED => "{$userName} logged a play in {$groupName}",
            GroupAuditLog::ACTION_EVENT_CREATED => "{$userName} created an event in {$groupName}",
            default => "{$userName} performed an action in {$groupName}",
        };
    }
}
