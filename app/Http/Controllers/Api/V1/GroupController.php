<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Group\StoreGroupRequest;
use App\Http\Requests\Group\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Services\GroupAuditLogService;
use App\Services\GroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        
        $query = Group::query();

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

        // Rate limit is checked by ThrottleGroupCreation middleware
        // No need to check again here

        $group = DB::transaction(function () use ($request, $user): Group {
            $group = $this->groupService->createGroup($request->validated(), $user);
            $this->auditLogService->logGroupCreated($group, $user);
            return $group;
        });

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

        $oldData = $group->only(['friendly_name', 'description', 'group_location', 'website_link', 'discord_link', 'slack_link']);

        $group = DB::transaction(function () use ($request, $group, $user, $oldData): Group {
            $newData = $request->validated();
            $updatedGroup = $this->groupService->updateGroup($group, $newData);

            // Calculate changes for audit log
            $changes = [];
            foreach ($newData as $key => $value) {
                if (isset($oldData[$key]) && $oldData[$key] !== $value) {
                    $changes[$key] = [
                        'before' => $oldData[$key],
                        'after' => $value,
                    ];
                }
            }

            if (!empty($changes)) {
                $this->auditLogService->logGroupUpdated($updatedGroup, $user, $changes);
            }

            return $updatedGroup;
        });

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
}
