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
     * Get audit logs for a group.
     *
     * Retrieve audit logs for a specific group. Only group admins and system admins can view.
     *
     * @urlParam id required The ID of the group. Example: 1
     * @queryParam per_page integer The number of items per page. Maximum 100. Default: 15. Example: 20
     * @queryParam actions string Comma-separated list of actions to filter by. Example: created,updated
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
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
