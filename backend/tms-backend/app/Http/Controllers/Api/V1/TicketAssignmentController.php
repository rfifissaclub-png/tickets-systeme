<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\AssignTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketAssignmentController extends Controller
{
    public function __construct(private readonly TicketService $ticketService) {}

    public function __invoke(AssignTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('assign', $ticket);

        $agent = User::query()->findOrFail($request->validated('agent_id'));

        $ticket = $this->ticketService->assign($ticket, $agent);

        return ApiResponse::success(
            data: new TicketResource($ticket->load(['ticketType', 'client', 'agent'])),
            message: 'Ticket assigned.',
        );
    }
}
