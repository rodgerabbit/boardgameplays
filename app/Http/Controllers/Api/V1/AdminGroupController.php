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
     * Restore a soft-deleted group.
     *
     * Restore a group that was previously soft-deleted. Only system admins can restore.
     *
     * @urlParam id required The ID of the group to restore. Example: 1
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
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
     * List soft-deleted groups.
     *
     * Get a paginated list of all soft-deleted groups. Only system admins can view.
     *
     * @queryParam per_page integer The number of items per page. Maximum 100. Default: 15. Example: 20
     *
     * @param Request $request
     * @return JsonResponse
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
