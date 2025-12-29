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
     * List groups.
     *
     * Get a paginated list of all groups in the system.
     *
     * @queryParam per_page integer The number of items per page. Maximum 100. Default: 15. Example: 20
     * @queryParam include string Comma-separated list of relationships to include (member_count, members, audit_log_count). Example: member_count,members
     *
     * @param Request $request
     * @return JsonResponse
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
     * Create a new group.
     *
     * Create a new group and add the creator as an admin. Rate limited to 1 per 5 minutes.
     *
     * @param StoreGroupRequest $request
     * @return JsonResponse
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
     * Get group details.
     *
     * Retrieve detailed information about a specific group.
     *
     * @urlParam id required The ID of the group. Example: 1
     * @queryParam include string Comma-separated list of relationships to include (members, audit_log_count). Example: members
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
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
     * Update a group.
     *
     * Update group properties. Rate limited to 1 per 10 seconds. Only group admins can update.
     *
     * @urlParam id required The ID of the group. Example: 1
     *
     * @param UpdateGroupRequest $request
     * @param string $id
     * @return JsonResponse
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
     * Delete a group.
     *
     * Soft delete a group. Only group admins can delete.
     *
     * @urlParam id required The ID of the group. Example: 1
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
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
