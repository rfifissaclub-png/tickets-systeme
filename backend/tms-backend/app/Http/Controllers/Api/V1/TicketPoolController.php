<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Tickets\TicketPoolService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketPoolController extends Controller
{
    public function __construct(private readonly TicketPoolService $poolService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $tickets = $this->poolService->poolQuery($request->user())
            ->with(['ticketType', 'client'])
            ->orderBy('created_at')
            ->paginate(min((int) $request->query('per_page', 25), 100));

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

    public function pull(Request $request, string $ticket): JsonResponse
    {
        // Not route-model-bound on purpose: pull() takes the raw id and does
        // its own SELECT ... FOR UPDATE inside a transaction — a bound model
        // here would be a stale, unlocked read from before the race window.
        $this->authorize('pull', Ticket::query()->findOrFail($ticket));

        $assigned = $this->poolService->pull($ticket, $request->user());

        return ApiResponse::success(
            data: new TicketResource($assigned->load(['ticketType', 'client', 'agent'])),
            message: __('messages.tickets.pulled'),
        );
    }
}
