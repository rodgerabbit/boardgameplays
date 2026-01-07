<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Services\GroupAuditLogService;
use App\Services\GroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Admin - Groups
 *
 * Admin APIs for managing groups (system admin only).
 */
class AdminGroupController extends BaseApiController
{
    /**
     * Create a new AdminGroupController instance.
     */
    public function __construct(
        private readonly GroupService $groupService,
        private readonly GroupAuditLogService $auditLogService,
    ) {
        $this->middleware(function ($request, $next) {
            if (!$request->user()?->isAdmin()) {
                abort(403, 'Only system administrators can access this endpoint.');
            }
            return $next($request);
        });
    }

    /**
     * Restore a soft-deleted group
     *
     * Restore a group that was previously soft-deleted. Only system admins can restore groups.
     *
     * @urlParam id required The ID of the group to restore. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Group restored successfully.",
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
     * @response 403 {
     *   "message": "Only system administrators can access this endpoint."
     * }
     *
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Group] 1"
     * }
     *
     * @authenticated
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        $group = Group::onlyTrashed()->findOrFail($id);
        $user = $request->user();

        DB::transaction(function () use ($group, $user): void {
            $this->groupService->restoreGroup($group);
            $this->auditLogService->logGroupRestored($group, $user);
        });

        return $this->successResponse(
            new GroupResource($group->fresh()),
            'Group restored successfully.'
        );
    }

    /**
     * List soft-deleted groups
     *
     * Get a paginated list of all soft-deleted groups. Only system admins can view deleted groups.
     *
     * @queryParam per_page integer The number of items per page. Maximum 100. Default: 15. Example: 20
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
     *       "updated_at": "2025-12-17T19:52:30+00:00"
     *     }
     *   ],
     *   "links": {
     *     "first": "http://localhost/api/v1/admin/groups/deleted?page=1",
     *     "last": "http://localhost/api/v1/admin/groups/deleted?page=3",
     *     "prev": null,
     *     "next": "http://localhost/api/v1/admin/groups/deleted?page=2"
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "from": 1,
     *     "last_page": 3,
     *     "path": "http://localhost/api/v1/admin/groups/deleted",
     *     "per_page": 15,
     *     "to": 15,
     *     "total": 45
     *   }
     * }
     *
     * @response 403 {
     *   "message": "Only system administrators can access this endpoint."
     * }
     *
     * @authenticated
     */
    public function indexDeleted(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);
        $groups = Group::onlyTrashed()
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);

        return GroupResource::collection($groups)->response();
    }
}
