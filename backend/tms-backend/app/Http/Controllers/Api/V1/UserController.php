<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Users\UserManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserManagementService $userService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = $this->userService->paginate(
            $request->user(),
            $request->query('tenant_id'),
            min((int) $request->query('per_page', 25), 100),
        );

        return ApiResponse::success(
            data: UserResource::collection($users->items()),
            meta: [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->user(), $request->validated());

        return ApiResponse::success(new UserResource($user->load('roles')), __('messages.users.created'), 201);
    }

    public function show(Request $request, string $user): JsonResponse
    {
        $model = $this->userService->find($request->user(), $user);

        $this->authorize('view', $model);

        return ApiResponse::success(new UserResource($model));
    }

    public function update(UpdateUserRequest $request, string $user): JsonResponse
    {
        $model = $this->userService->find($request->user(), $user);

        $this->authorize('update', $model);

        $model = $this->userService->update($request->user(), $model, $request->validated());

        return ApiResponse::success(new UserResource($model->load('roles')), __('messages.users.updated'));
    }

    public function destroy(Request $request, string $user): JsonResponse
    {
        $model = $this->userService->find($request->user(), $user);

        $this->authorize('delete', $model);

        $this->userService->delete($request->user(), $model);

        return ApiResponse::success(message: __('messages.users.deleted'));
    }
}
