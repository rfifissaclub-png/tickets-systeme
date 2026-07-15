<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\TransitionTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Tenancy\TenantContext;
use App\Services\Tickets\TicketService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketTransitionController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function __invoke(TransitionTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('transition', $ticket);

        // The status update itself is an UPDATE on `tickets`, gated by RLS
        // the same way SELECTs are — and the notification recipients
        // (client/agent) it looks up along the way are themselves
        // cross-tenant users for a Super Admin acting outside their own
        // (nonexistent) tenant.
        $ticket = $this->tenantContext->runScoped(
            'platform-wide ticket status transition (super admin)',
            fn () => $this->ticketService->transitionStatus($ticket, TicketStatus::from($request->validated('status')))
                ->load(['ticketType', 'client', 'agent']),
        );

        return ApiResponse::success(
            data: new TicketResource($ticket),
            message: __('messages.tickets.status_updated'),
        );
    }
}
