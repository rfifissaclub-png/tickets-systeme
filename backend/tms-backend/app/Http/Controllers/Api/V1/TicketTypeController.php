<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketTypes\StoreTicketTypeRequest;
use App\Http\Requests\TicketTypes\UpdateTicketTypeRequest;
use App\Http\Resources\TicketTypeResource;
use App\Models\TicketType;
use App\Services\Tenancy\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketTypeController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', TicketType::class);

        $ticketTypes = TicketType::query()->where('is_active', true)->orderBy('name')->get();

        return ApiResponse::success(TicketTypeResource::collection($ticketTypes));
    }

    public function store(StoreTicketTypeRequest $request): JsonResponse
    {
        $ticketType = TicketType::query()->create([
            'tenant_id' => $this->tenantContext->requireTenant(),
            ...$request->validated(),
        ]);

        return ApiResponse::success(new TicketTypeResource($ticketType), 'Ticket type created.', 201);
    }

    public function update(UpdateTicketTypeRequest $request, TicketType $ticketType): JsonResponse
    {
        $this->authorize('update', $ticketType);

        $ticketType->fill($request->validated())->save();

        return ApiResponse::success(new TicketTypeResource($ticketType), 'Ticket type updated.');
    }

    public function destroy(TicketType $ticketType): JsonResponse
    {
        $this->authorize('delete', $ticketType);

        $ticketType->delete();

        return ApiResponse::success(message: 'Ticket type deleted.');
    }
}
