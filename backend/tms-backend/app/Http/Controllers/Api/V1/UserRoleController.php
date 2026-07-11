<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\AssignUserRolesRequest;
use App\Http\Resources\UserResource;
use App\Services\Users\UserManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserRoleController extends Controller
{
    public function __construct(private readonly UserManagementService $userService) {}

    public function __invoke(AssignUserRolesRequest $request, string $user): JsonResponse
    {
        $target = $this->userService->find($request->user(), $user);

        $this->authorize('manageRoles', $target);

        $target = $this->userService->syncRoles($request->user(), $target, $request->validated('roles'));

        return ApiResponse::success(new UserResource($target), __('messages.users.roles_updated'));
    }
}
