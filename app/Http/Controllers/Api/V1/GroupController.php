<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Group\StoreGroupRequest;
use App\Http\Requests\Group\UpdateGroupRequest;
use App\Http\Resources\GroupActivityResource;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\GroupMember;
use App\Services\GroupAuditLogService;
use App\Services\GroupMemberStatisticsService;
use App\Services\GroupStatisticsService;
use App\Services\GroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @group Groups
 *
 * APIs for managing groups.
 */
class GroupController extends BaseApiController
{
    /**
     * Create a new GroupController instance.
     */
    public function __construct(
        private readonly GroupService $groupService,
        private readonly GroupAuditLogService $auditLogService,
        private readonly GroupStatisticsService $groupStatisticsService,
        private readonly GroupMemberStatisticsService $groupMemberStatisticsService,
    ) {
    }

    /**
     * List groups
     *
     * Get a paginated list of all groups in the system. Only groups the user has access to will be returned.
     *
     * @queryParam per_page integer The number of items per page. Maximum 100. Default: 15. Example: 20
     * @queryParam include string Comma-separated list of relationships to include (member_count, members, audit_log_count). Example: member_count,members
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "friendly_name": "My Gaming Group",
     *       "description": "A group for board game enthusiasts",
     *       "group_location": "New York, NY",
     *       "website_link": "https://example.com",
     *       "discord_link": "https://discord.gg/example",
     *       "slack_link": null,
     *       "created_by_user_id": 1,
     *       "created_at": "2025-12-17T19:52:30+00:00",
     *       "updated_at": "2025-12-17T19:52:30+00:00",
     *       "member_count": 5,
     *       "audit_log_count": 3
     *     }
     *   ],
     *   "links": {
     *     "first": "http://localhost/api/v1/groups?page=1",
     *     "last": "http://localhost/api/v1/groups?page=10",
     *     "prev": null,
     *     "next": "http://localhost/api/v1/groups?page=2"
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "from": 1,
     *     "last_page": 10,
     *     "path": "http://localhost/api/v1/groups",
     *     "per_page": 15,
     *     "to": 15,
     *     "total": 150
     *   }
     * }
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Group::class);
        $user = $request->user();

        $query = Group::query();

        if ($request->boolean('browse')) {
            $query->whereIn('visibility', [Group::VISIBILITY_VIEWABLE, Group::VISIBILITY_PUBLICLY_JOINABLE])
                ->whereDoesntHave('groupMembers', fn ($q) => $q->where('user_id', $user->id));
        }

        $includes = explode(',', $request->get('include', ''));
        if (in_array('members', $includes)) {
            $query->with('groupMembers.user');
        }
        if (in_array('audit_log_count', $includes)) {
            $query->withCount('auditLogs');
        }

        $perPage = min((int) $request->get('per_page', 15), 100);
        $groups = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return GroupResource::collection($groups)->response();
    }

    /**
     * List groups the current user is a member of (for Groups page).
     */
    public function myGroups(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Group::query()
            ->whereHas('groupMembers', fn ($q) => $q->where('user_id', $user->id))
            ->with(['groupMembers.user'])
            ->withLastActiveAt();

        $includes = explode(',', $request->get('include', ''));
        if (in_array('member_count', $includes) || true) {
            $query->withCount('groupMembers');
        }

        $groups = $query->orderBy('created_at', 'desc')->get();

        foreach ($groups as $group) {
            $membership = $group->groupMembers->firstWhere('user_id', $user->id);
            $group->current_user_is_admin = $membership?->role === GroupMember::ROLE_GROUP_ADMIN;
        }

        return $this->successResponse(GroupResource::collection($groups));
    }

    /**
     * Paginated activity across all groups the current user is a member of.
     */
    public function myGroupsActivity(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min((int) $request->get('per_page', 15), 50);
        $paginator = $this->auditLogService->getActivityForUserGroups($user, $perPage);

        return GroupActivityResource::collection($paginator)->response();
    }

    /**
     * Create a new group
     *
     * Create a new group and add the creator as an admin. Rate limited to 1 per 5 minutes.
     *
     * @bodyParam friendly_name string required The name of the group. Example: My Gaming Group
     * @bodyParam description string A description of the group. Example: A group for board game enthusiasts
     * @bodyParam group_location string The location of the group. Example: New York, NY
     * @bodyParam website_link string A URL to the group's website. Must be a valid URL. Example: https://example.com
     * @bodyParam discord_link string A URL to the group's Discord server. Must be a valid URL. Example: https://discord.gg/example
     * @bodyParam slack_link string A URL to the group's Slack workspace. Must be a valid URL. Example: https://example.slack.com
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Group created successfully.",
     *   "data": {
     *     "id": 1,
     *     "friendly_name": "My Gaming Group",
     *     "description": "A group for board game enthusiasts",
     *     "group_location": "New York, NY",
     *     "website_link": "https://example.com",
     *     "discord_link": "https://discord.gg/example",
     *     "slack_link": null,
     *     "created_by_user_id": 1,
     *     "created_at": "2025-12-17T19:52:30+00:00",
     *     "updated_at": "2025-12-17T19:52:30+00:00"
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "friendly_name": ["The friendly name field is required."],
     *     "website_link": ["The website link must be a valid URL."]
     *   }
     * }
     *
     * @response 429 {
     *   "message": "Too Many Attempts."
     * }
     *
     * @authenticated
     */
    public function store(StoreGroupRequest $request): JsonResponse
    {
        $this->authorize('create', Group::class);
        $user = $request->user();

        $validated = $request->validated();
        $photo = $request->file('photo');
        unset($validated['photo']);
        if (! isset($validated['visibility'])) {
            $validated['visibility'] = Group::VISIBILITY_PRIVATE;
        }

        $group = DB::transaction(function () use ($validated, $user): Group {
            $group = $this->groupService->createGroup($validated, $user);
            $this->auditLogService->logGroupCreated($group, $user);
            return $group;
        });

        if ($photo !== null) {
            $directory = 'groups/' . $group->id;
            $path = $photo->store($directory, 'public');
            $group->update(['photo_path' => $path]);
            $group->refresh();
        }

        return $this->successResponse(
            new GroupResource($group),
            'Group created successfully.',
            201
        );
    }

    /**
     * Get group details
     *
     * Retrieve detailed information about a specific group. Only users with access to the group can view it.
     *
     * @urlParam id required The ID of the group. Example: 1
     * @queryParam include string Comma-separated list of relationships to include (members, audit_log_count). Example: members
     *
     * @response 200 {
     *   "success": true,
     *   "message": null,
     *   "data": {
     *     "id": 1,
     *     "friendly_name": "My Gaming Group",
     *     "description": "A group for board game enthusiasts",
     *     "group_location": "New York, NY",
     *     "website_link": "https://example.com",
     *     "discord_link": "https://discord.gg/example",
     *     "slack_link": null,
     *     "created_by_user_id": 1,
     *     "created_at": "2025-12-17T19:52:30+00:00",
     *     "updated_at": "2025-12-17T19:52:30+00:00",
     *     "audit_log_count": 3
     *   }
     * }
     *
     * @response 403 {
     *   "message": "This action is unauthorized."
     * }
     *
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Group] 1"
     * }
     *
     * @authenticated
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $this->authorize('view', $group);

        $includes = explode(',', $request->get('include', ''));
        if (in_array('members', $includes)) {
            $group->load('groupMembers.user');
        }
        if (in_array('audit_log_count', $includes)) {
            $group->loadCount('auditLogs');
        }

        return $this->successResponse(new GroupResource($group));
    }

    /**
     * Get overview statistics for the group (monthly activity, locations, top games, categories).
     */
    public function overview(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $this->authorize('view', $group);

        $monthlyActivity = $this->groupStatisticsService->getMonthlyActivityByYear($group);
        $locationDistribution = $this->groupStatisticsService->getLocationDistribution($group);
        $topGamesByTime = $this->groupStatisticsService->getTopGamesByTime($group, 10);
        $categoryDistribution = $this->groupStatisticsService->getCategoryDistribution($group);

        return $this->successResponse([
            'monthly_activity' => $monthlyActivity,
            'location_distribution' => $locationDistribution,
            'top_games_by_time' => $topGamesByTime,
            'category_distribution' => $categoryDistribution,
        ]);
    }

    /**
     * Get per-member statistics for the group (including H-index).
     */
    public function memberStatistics(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $this->authorize('view', $group);

        $statistics = $this->groupMemberStatisticsService->getMemberStatistics($group);

        return $this->successResponse(['members' => $statistics]);
    }

    /**
     * Get paginated list of games played by the group, sorted by play count.
     */
    public function games(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $this->authorize('view', $group);

        $perPage = min((int) $request->get('per_page', 15), 50);
        $page = max(1, (int) $request->get('page', 1));
        $search = $request->get('search');
        $searchString = is_string($search) ? trim($search) : null;
        if ($searchString === '') {
            $searchString = null;
        }

        $result = $this->groupStatisticsService->getGamesPlayedByGroup($group, $perPage, $page, $searchString);

        return $this->successResponse($result);
    }

    /**
     * Update a group
     *
     * Update group properties. Rate limited to 1 per 10 seconds. Only group admins can update.
     *
     * @urlParam id required The ID of the group. Example: 1
     * @bodyParam friendly_name string The name of the group. Example: My Gaming Group
     * @bodyParam description string A description of the group. Example: A group for board game enthusiasts
     * @bodyParam group_location string The location of the group. Example: New York, NY
     * @bodyParam website_link string A URL to the group's website. Must be a valid URL. Example: https://example.com
     * @bodyParam discord_link string A URL to the group's Discord server. Must be a valid URL. Example: https://discord.gg/example
     * @bodyParam slack_link string A URL to the group's Slack workspace. Must be a valid URL. Example: https://example.slack.com
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Group updated successfully.",
     *   "data": {
     *     "id": 1,
     *     "friendly_name": "My Updated Gaming Group",
     *     "description": "An updated description",
     *     "group_location": "New York, NY",
     *     "website_link": "https://example.com",
     *     "discord_link": "https://discord.gg/example",
     *     "slack_link": null,
     *     "created_by_user_id": 1,
     *     "created_at": "2025-12-17T19:52:30+00:00",
     *     "updated_at": "2025-12-17T20:00:00+00:00"
     *   }
     * }
     *
     * @response 403 {
     *   "message": "This action is unauthorized."
     * }
     *
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Group] 1"
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "website_link": ["The website link must be a valid URL."]
     *   }
     * }
     *
     * @response 429 {
     *   "message": "Too Many Attempts."
     * }
     *
     * @authenticated
     */
    public function update(UpdateGroupRequest $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $this->authorize('update', $group);
        $user = $request->user();

        // Rate limit is checked by ThrottleGroupUpdate middleware
        // No need to check again here

        $oldData = $group->only(['friendly_name', 'description', 'group_location', 'website_link', 'discord_link', 'slack_link', 'visibility', 'group_settings']);

        $validated = $request->validated();
        $photo = $request->file('photo');
        if (isset($validated['photo'])) {
            unset($validated['photo']);
        }

        $group = DB::transaction(function () use ($validated, $group, $user, $oldData): Group {
            $updatedGroup = $this->groupService->updateGroup($group, $validated);

            // Calculate changes for audit log
            $changes = [];
            foreach ($validated as $key => $value) {
                if (array_key_exists($key, $oldData) && $oldData[$key] != $value) {
                    $changes[$key] = [
                        'before' => $oldData[$key],
                        'after' => $value,
                    ];
                }
            }

            if (! empty($changes)) {
                $this->auditLogService->logGroupUpdated($updatedGroup, $user, $changes);
            }

            return $updatedGroup;
        });

        if ($photo !== null) {
            $directory = 'groups/' . $group->id;
            if ($group->photo_path) {
                Storage::disk('public')->delete($group->photo_path);
            }
            $path = $photo->store($directory, 'public');
            $group->update(['photo_path' => $path]);
            $group->refresh();
        }

        return $this->successResponse(
            new GroupResource($group),
            'Group updated successfully.'
        );
    }

    /**
     * Delete a group
     *
     * Soft delete a group. Only group admins can delete. The group can be restored by system administrators.
     *
     * @urlParam id required The ID of the group. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Group deleted successfully.",
     *   "data": null
     * }
     *
     * @response 403 {
     *   "message": "This action is unauthorized."
     * }
     *
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Group] 1"
     * }
     *
     * @authenticated
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $this->authorize('delete', $group);
        $user = $request->user();

        DB::transaction(function () use ($group, $user): void {
            $this->groupService->deleteGroup($group);
            $this->auditLogService->logGroupDeleted($group, $user);
        });

        return $this->successResponse(
            null,
            'Group deleted successfully.'
        );
    }

    /**
     * Join a publicly joinable group. For viewable/private groups use invite link.
     */
    public function join(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $user = $request->user();

        if ($group->visibility !== Group::VISIBILITY_PUBLICLY_JOINABLE) {
            return $this->errorResponse('This group can only be joined via invitation.', 403);
        }

        if ($group->groupMembers()->where('user_id', $user->id)->exists()) {
            return $this->successResponse(new GroupResource($group->load('groupMembers.user')), 'Already a member.');
        }

        $this->groupService->addMemberToGroup($group, $user);

        return $this->successResponse(
            new GroupResource($group->fresh(['groupMembers.user'])),
            'You have joined the group.',
            201
        );
    }

    /**
     * Create an invitation for the group. Only group admins can create invites.
     */
    public function createInvite(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $this->authorize('update', $group);
        $user = $request->user();

        $expiresAt = $request->input('expires_at') ? \Carbon\Carbon::parse($request->input('expires_at')) : null;
        $maxUses = $request->input('max_uses') ? (int) $request->input('max_uses') : null;

        $invite = $this->groupService->createInvite($group, $user, $expiresAt, $maxUses);
        $inviteUrl = url('/groups/join/' . $invite->token);

        return $this->successResponse([
            'invite_url' => $inviteUrl,
            'token' => $invite->token,
            'expires_at' => $invite->expires_at?->toIso8601String(),
            'max_uses' => $invite->max_uses,
        ], 'Invitation created.', 201);
    }

    /**
     * Update a group member's role. Admin only. Cannot demote the last admin.
     */
    public function updateMember(Request $request, string $id, string $userId): JsonResponse
    {
        $group = Group::findOrFail($id);
        $this->authorize('update', $group);

        $request->validate(['role' => ['required', 'string', 'in:group_admin,group_member']]);

        $targetUser = \App\Models\User::findOrFail($userId);
        $membership = GroupMember::where('group_id', $group->id)->where('user_id', $targetUser->id)->firstOrFail();

        $adminCount = $group->groupMembers()->where('role', GroupMember::ROLE_GROUP_ADMIN)->count();
        if ($membership->role === GroupMember::ROLE_GROUP_ADMIN && $adminCount <= 1 && $request->input('role') !== GroupMember::ROLE_GROUP_ADMIN) {
            return $this->errorResponse('Cannot demote the last admin. Promote another member first.', 422);
        }

        if ($request->input('role') === GroupMember::ROLE_GROUP_ADMIN) {
            $this->groupService->promoteMemberToAdmin($group, $targetUser);
        } else {
            $this->groupService->demoteAdminToMember($group, $targetUser);
        }

        $group->load('groupMembers.user');

        return $this->successResponse(new GroupResource($group), 'Member updated.');
    }

    /**
     * Remove a member from the group. Admin only. Cannot remove the last admin.
     */
    public function destroyMember(Request $request, string $id, string $userId): JsonResponse
    {
        $group = Group::findOrFail($id);
        $this->authorize('update', $group);

        $targetUser = \App\Models\User::findOrFail($userId);
        $membership = GroupMember::where('group_id', $group->id)->where('user_id', $targetUser->id)->firstOrFail();

        $adminCount = $group->groupMembers()->where('role', GroupMember::ROLE_GROUP_ADMIN)->count();
        if ($membership->role === GroupMember::ROLE_GROUP_ADMIN && $adminCount <= 1) {
            return $this->errorResponse('Cannot remove the last admin. Promote another member first.', 422);
        }

        $this->groupService->removeMemberFromGroup($group, $targetUser);
        $group->load('groupMembers.user');

        return $this->successResponse(new GroupResource($group), 'Member removed.');
    }

    /**
     * Get the current valid invite for the group, if any. Any group member can view.
     */
    public function indexInvites(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $this->authorize('view', $group);

        $invite = $group->groupInvites()->valid()->first();
        if ($invite === null) {
            return $this->successResponse(['invite_url' => null, 'has_valid_invite' => false]);
        }

        $inviteUrl = url('/groups/join/' . $invite->token);

        return $this->successResponse([
            'invite_url' => $inviteUrl,
            'has_valid_invite' => true,
            'expires_at' => $invite->expires_at?->toIso8601String(),
            'max_uses' => $invite->max_uses,
            'times_used' => $invite->times_used,
        ]);
    }

    /**
     * Regenerate the group invite link (invalidates previous link). Admin only.
     */
    public function regenerateInvite(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $this->authorize('update', $group);
        $user = $request->user();

        $invite = $this->groupService->regenerateInvite($group, $user);
        $inviteUrl = url('/groups/join/' . $invite->token);

        return $this->successResponse([
            'invite_url' => $inviteUrl,
            'token' => $invite->token,
        ], 'Invitation link regenerated.');
    }

    /**
     * Revoke the current invite link (disable invitations). Admin only.
     */
    public function revokeInvite(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $this->authorize('update', $group);

        $revoked = $this->groupService->revokeInvitesForGroup($group);

        return $this->successResponse(
            ['revoked_count' => $revoked],
            $revoked > 0 ? 'Invitation link disabled.' : 'No active invitation link to disable.'
        );
    }
}
