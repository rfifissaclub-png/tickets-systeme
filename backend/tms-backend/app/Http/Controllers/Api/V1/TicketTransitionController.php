<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\TransitionTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Tickets\TicketService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketTransitionController extends Controller
{
    public function __construct(private readonly TicketService $ticketService) {}

    public function __invoke(TransitionTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('transition', $ticket);

        $ticket = $this->ticketService->transitionStatus(
            $ticket,
            TicketStatus::from($request->validated('status')),
        );

        return ApiResponse::success(
            data: new TicketResource($ticket->load(['ticketType', 'client', 'agent'])),
            message: 'Ticket status updated.',
        );
    }
}
