<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Requests\Tickets\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Tenancy\TenantContext;
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
        private readonly TenantContext $tenantContext,
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

        // TicketService::create() only keeps its Super Admin bypass active
        // for as long as it runs — a cross-tenant client is otherwise
        // invisible under RLS by the time this eager-load runs afterward.
        $this->tenantContext->runScoped(
            'platform-wide ticket creation (super admin)',
            fn () => $ticket->load(['ticketType', 'client', 'agent']),
        );

        return ApiResponse::success(
            data: new TicketResource($ticket),
            message: __('messages.tickets.created'),
            status: 201,
        );
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        // The route binding for {ticket} already handled a Super Admin's
        // cross-tenant lookup of the ticket itself (see AppServiceProvider)
        // — but that bypass ends there, so the client/agent relations below
        // (their own users, possibly in yet another tenant) need their own.
        $this->tenantContext->runScoped(
            'platform-wide ticket lookup (super admin)',
            fn () => $ticket->load(['ticketType', 'client', 'agent']),
        );

        return ApiResponse::success(new TicketResource($ticket));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $ticket = $this->tenantContext->runScoped(
            'platform-wide ticket update (super admin)',
            fn () => $this->ticketService->update($ticket, $request->validated())->load(['ticketType', 'client', 'agent']),
        );

        return ApiResponse::success(
            data: new TicketResource($ticket),
            message: __('messages.tickets.updated'),
        );
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $this->authorize('delete', $ticket);

        $this->tenantContext->runScoped('platform-wide ticket deletion (super admin)', fn () => $ticket->delete());

        return ApiResponse::success(message: __('messages.tickets.deleted'));
    }
}
