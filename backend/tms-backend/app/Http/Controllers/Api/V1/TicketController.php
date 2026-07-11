<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Requests\Tickets\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Tickets\TicketSearchService;
use App\Services\Tickets\TicketService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
        private readonly TicketSearchService $ticketSearchService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $tickets = $this->ticketSearchService->search($request->user(), $request);

        return ApiResponse::success(
            data: TicketResource::collection($tickets->items()),
            meta: [
                'current_page' => $tickets->currentPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
                'last_page' => $tickets->lastPage(),
            ],
        );
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->create($request->user(), $request->validated());

        return ApiResponse::success(
            data: new TicketResource($ticket->load(['ticketType', 'client', 'agent'])),
            message: 'Ticket created.',
            status: 201,
        );
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        return ApiResponse::success(new TicketResource($ticket->load(['ticketType', 'client', 'agent'])));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $ticket = $this->ticketService->update($ticket, $request->validated());

        return ApiResponse::success(
            data: new TicketResource($ticket->load(['ticketType', 'client', 'agent'])),
            message: 'Ticket updated.',
        );
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $this->authorize('delete', $ticket);

        $ticket->delete();

        return ApiResponse::success(message: 'Ticket deleted.');
    }
}
