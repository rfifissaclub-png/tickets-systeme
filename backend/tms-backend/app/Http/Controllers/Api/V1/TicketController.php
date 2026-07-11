<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Requests\Tickets\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Tickets\TicketService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    private const SORTABLE = ['created_at', 'updated_at', 'priority', 'status'];

    public function __construct(private readonly TicketService $ticketService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $actor = $request->user();
        $query = Ticket::query()->with(['ticketType', 'client', 'agent']);

        // tickets.viewAny controls scope, not endpoint access (see
        // TicketPolicy::viewAny): without it, a caller only ever sees
        // tickets they own as client or are assigned as agent.
        if (! $actor->can('tickets.viewAny')) {
            $query->where(function ($q) use ($actor) {
                $q->where('client_id', $actor->id)->orWhere('agent_id', $actor->id);
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        if ($agentId = $request->query('agent_id')) {
            $query->where('agent_id', $agentId);
        }

        $sort = (string) $request->query('sort', '-created_at');
        $column = ltrim($sort, '-');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $query->orderBy(in_array($column, self::SORTABLE, true) ? $column : 'created_at', $direction);

        $perPage = min((int) $request->query('per_page', 25), 100);
        $tickets = $query->paginate($perPage);

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
