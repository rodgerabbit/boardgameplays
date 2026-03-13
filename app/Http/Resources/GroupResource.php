<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Group model.
 *
 * Transforms the Group model into a consistent JSON structure for API responses.
 */
class GroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'friendly_name' => $this->friendly_name,
            'description' => $this->description,
            'group_location' => $this->group_location,
            'website_link' => $this->website_link,
            'discord_link' => $this->discord_link,
            'slack_link' => $this->slack_link,
            'photo_url' => $this->photo_url,
            'visibility' => $this->visibility ?? \App\Models\Group::VISIBILITY_PRIVATE,
            'created_by_user_id' => $this->created_by_user_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if (isset($this->last_active_at)) {
            $data['last_active_at'] = $this->last_active_at?->toIso8601String();
        }

        if (isset($this->current_user_is_admin)) {
            $data['current_user_is_admin'] = (bool) $this->current_user_is_admin;
        }

        // Include member count if requested
        if ($request->has('include') && str_contains($request->get('include', ''), 'member_count')) {
            $data['member_count'] = $this->member_count ?? $this->groupMembers()->count();
        }

        // Include members if requested
        if ($request->has('include') && str_contains($request->get('include', ''), 'members')) {
            $data['members'] = GroupMemberResource::collection($this->whenLoaded('groupMembers'));
        }

        // Include audit log count if requested
        if ($request->has('include') && str_contains($request->get('include', ''), 'audit_log_count')) {
            $data['audit_log_count'] = $this->auditLogs()->count();
        }

        // Include creator if requested
        if ($request->has('include') && str_contains($request->get('include', ''), 'creator')) {
            $data['creator'] = new UserResource($this->whenLoaded('creator'));
        }

        return $data;
    }
}
