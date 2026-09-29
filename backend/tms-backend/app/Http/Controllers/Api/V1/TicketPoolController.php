<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Tenancy\TenantContext;
use App\Services\Tickets\TicketPoolService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketPoolController extends Controller
{
    public function __construct(
        private readonly TicketPoolService $poolService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $tickets = $this->poolService->poolQuery($request->user(), min((int) $request->query('per_page', 25), 100));

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

    public function agents(Request $request): JsonResponse
    {
        $this->authorize('assignAny', Ticket::class);

        $agents = $this->poolService->agentWorkload(
            $request->user(),
            $request->query('tenant_id'),
            $request->query('ticket_type_id'),
        );

        return ApiResponse::success(data: $agents);
    }

    public function pull(Request $request, string $ticket): JsonResponse
    {
        // Not route-model-bound on purpose: pull() takes the raw id and does
        // its own SELECT ... FOR UPDATE inside a transaction — a bound model
        // here would be a stale, unlocked read from before the race window.
        //
        // A Super Admin has no tenant bound at all, so this lookup (like
        // every other tenant-owned read) would otherwise fail closed under
        // RLS before the policy even runs — same reasoning as
        // TicketPoolService's own Super Admin handling.
        // Normalise the incoming ticket identifier: tests (and some
        // clients) may accidentally pass a JSON-encoded ticket object or an
        // array instead of a raw id string. Defensively accept any of the
        // above and extract the actual id to avoid passing invalid values
        // to Eloquent's findOrFail(), which expects a scalar primary key.
        $ticketId = $ticket;

        if (is_string($ticket) && str_starts_with($ticket, '{')) {
            $decoded = json_decode($ticket, true);
            if (is_array($decoded) && array_key_exists('id', $decoded)) {
                $ticketId = $decoded['id'];
            }
        } elseif (is_array($ticket) && array_key_exists('id', $ticket)) {
            $ticketId = $ticket['id'];
        }

        $ticketModel = $this->tenantContext->runScoped(
            'platform-wide ticket pool (super admin)',
            fn () => Ticket::query()->findOrFail($ticketId),
        );

        $this->authorize('pull', $ticketModel);

        $assigned = $this->poolService->pull($ticketId, $request->user());

        // Same Super Admin handling as the lookup above: pull() only keeps
        // its own bypass active for as long as it runs, and eager-loading
        // these relations happens after it's already returned — including
        // 'agent', which for a Super Admin is their own user row, itself
        // invisible under RLS without a tenant bound.
        $this->tenantContext->runScoped(
            'platform-wide ticket pool (super admin)',
            fn () => $assigned->load(['ticketType', 'client', 'agent']),
        );

        return ApiResponse::success(
            data: new TicketResource($assigned),
            message: __('messages.tickets.pulled'),
        );
    }
}
