<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\AssignTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Services\Tickets\TicketService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketAssignmentController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function __invoke(AssignTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('assign', $ticket);

        // The agent lookup, the assignment (an UPDATE on `tickets`), and
        // eager-loading the response are all cross-tenant for a Super
        // Admin — see AssignTicketRequest's own comment on why its usual
        // exists/role checks are skipped for them, and
        // TicketService::assign()'s own comment on where those checks are
        // enforced instead.
        $ticket = $this->tenantContext->runScoped(
            'platform-wide ticket assignment (super admin)',
            function () use ($request, $ticket) {
                $agent = User::query()->findOrFail($request->validated('agent_id'));

                return $this->ticketService->assign($ticket, $agent)->load(['ticketType', 'client', 'agent']);
            },
        );

        return ApiResponse::success(
            data: new TicketResource($ticket),
            message: __('messages.tickets.assigned'),
        );
    }
}
