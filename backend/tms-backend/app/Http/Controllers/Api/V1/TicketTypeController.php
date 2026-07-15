<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketTypes\StoreTicketTypeRequest;
use App\Http\Requests\TicketTypes\UpdateTicketTypeRequest;
use App\Http\Resources\TicketTypeResource;
use App\Models\TicketType;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', TicketType::class);

        $ticketTypes = TicketType::query()->where('is_active', true)->orderBy('name')->get();

        return ApiResponse::success(TicketTypeResource::collection($ticketTypes));
    }

    public function store(StoreTicketTypeRequest $request): JsonResponse
    {
        $ticketType = TicketType::query()->create($request->validated());

        return ApiResponse::success(new TicketTypeResource($ticketType), __('messages.ticket_types.created'), 201);
    }

    public function update(UpdateTicketTypeRequest $request, TicketType $ticketType): JsonResponse
    {
        $this->authorize('update', $ticketType);

        $ticketType->fill($request->validated())->save();

        return ApiResponse::success(new TicketTypeResource($ticketType), __('messages.ticket_types.updated'));
    }

    public function destroy(TicketType $ticketType): JsonResponse
    {
        $this->authorize('delete', $ticketType);

        $ticketType->delete();

        return ApiResponse::success(message: __('messages.ticket_types.deleted'));
    }
}
