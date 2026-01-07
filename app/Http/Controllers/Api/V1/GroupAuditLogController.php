<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\GroupAuditLogResource;
use App\Models\Group;
use App\Services\GroupAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Group Audit Logs
 *
 * APIs for viewing group audit logs.
 */
class GroupAuditLogController extends BaseApiController
{
    /**
     * Create a new GroupAuditLogController instance.
     */
    public function __construct(
        private readonly GroupAuditLogService $auditLogService,
    ) {
    }

    /**
     * Get audit logs for a group
     *
     * Retrieve audit logs for a specific group. Only group admins and system admins can view.
     *
     * @urlParam id required The ID of the group. Example: 1
     * @queryParam per_page integer The number of items per page. Maximum 100. Default: 15. Example: 20
     * @queryParam actions string Comma-separated list of actions to filter by. Available actions: created, updated, deleted, restored. Example: created,updated
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "action": "created",
     *       "changes": null,
     *       "metadata": {
     *         "friendly_name": "My Gaming Group"
     *       },
     *       "user": {
     *         "id": 1,
     *         "name": "John Doe",
     *         "email": "user@example.com",
     *         "email_verified_at": "2025-12-17T19:52:30+00:00",
     *         "created_at": "2025-12-17T19:52:30+00:00",
     *         "updated_at": "2025-12-17T19:52:30+00:00"
     *       },
     *       "created_at": "2025-12-17T19:52:30+00:00"
     *     },
     *     {
     *       "id": 2,
     *       "action": "updated",
     *       "changes": {
     *         "friendly_name": {
     *           "before": "My Gaming Group",
     *           "after": "My Updated Gaming Group"
     *         }
     *       },
     *       "metadata": null,
     *       "user": {
     *         "id": 1,
     *         "name": "John Doe",
     *         "email": "user@example.com",
     *         "email_verified_at": "2025-12-17T19:52:30+00:00",
     *         "created_at": "2025-12-17T19:52:30+00:00",
     *         "updated_at": "2025-12-17T19:52:30+00:00"
     *       },
     *       "created_at": "2025-12-17T20:00:00+00:00"
     *     }
     *   ],
     *   "links": {
     *     "first": "http://localhost/api/v1/groups/1/audit-logs?page=1",
     *     "last": "http://localhost/api/v1/groups/1/audit-logs?page=5",
     *     "prev": null,
     *     "next": "http://localhost/api/v1/groups/1/audit-logs?page=2"
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "from": 1,
     *     "last_page": 5,
     *     "path": "http://localhost/api/v1/groups/1/audit-logs",
     *     "per_page": 15,
     *     "to": 15,
     *     "total": 75
     *   }
     * }
     *
     * @response 403 {
     *   "message": "You do not have permission to view audit logs for this group."
     * }
     *
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Group] 1"
     * }
     *
     * @authenticated
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $user = $request->user();

        // Check authorization
        if (!$user->isAdmin() && !$user->isGroupAdmin($group->id)) {
            abort(403, 'You do not have permission to view audit logs for this group.');
        }

        $perPage = min((int) $request->get('per_page', 15), 100);
        $actions = $request->has('actions') ? explode(',', $request->get('actions')) : null;

        $auditLogs = $this->auditLogService->getGroupAuditLogs($group, $perPage, $actions);

        return GroupAuditLogResource::collection($auditLogs)->response();
    }
}
