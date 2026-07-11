<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\StoreTenantRequest;
use App\Http\Requests\Tenants\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\Tenancy\TenantManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(private readonly TenantManagementService $tenantService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        $tenants = $this->tenantService->paginate(min((int) $request->query('per_page', 25), 100));

        return ApiResponse::success(
            data: TenantResource::collection($tenants->items()),
            meta: [
                'current_page' => $tenants->currentPage(),
                'per_page' => $tenants->perPage(),
                'total' => $tenants->total(),
                'last_page' => $tenants->lastPage(),
            ],
        );
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantService->create($request->validated());

        return ApiResponse::success(new TenantResource($tenant), __('messages.tenants.created'), 201);
    }

    public function show(Request $request, string $tenant): JsonResponse
    {
        $model = $this->tenantService->find($request->user(), $tenant);

        $this->authorize('view', $model);

        return ApiResponse::success(new TenantResource($model));
    }

    public function update(UpdateTenantRequest $request, string $tenant): JsonResponse
    {
        $model = $this->tenantService->find($request->user(), $tenant);

        $this->authorize('update', $model);

        $model = $this->tenantService->update($request->user(), $model, $request->validated());

        return ApiResponse::success(new TenantResource($model), __('messages.tenants.updated'));
    }

    public function destroy(Request $request, string $tenant): JsonResponse
    {
        $model = $this->tenantService->find($request->user(), $tenant);

        $this->authorize('delete', $model);

        $this->tenantService->delete($model);

        return ApiResponse::success(message: __('messages.tenants.deleted'));
    }
}
