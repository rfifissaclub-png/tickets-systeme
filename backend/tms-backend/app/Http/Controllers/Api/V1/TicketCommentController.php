<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketCommentRequest;
use App\Http\Resources\TicketCommentResource;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketCommentController extends Controller
{
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('viewAny', [TicketComment::class, $ticket]);

        $query = $ticket->comments()->with('author')->orderBy('created_at');

        if (! $request->user()->can('tickets.viewInternal')) {
            $query->where('is_internal', false);
        }

        return ApiResponse::success(TicketCommentResource::collection($query->get()));
    }

    public function store(StoreTicketCommentRequest $request, Ticket $ticket): JsonResponse
    {
        $isInternal = (bool) $request->validated('is_internal', false);

        $this->authorize($isInternal ? 'createInternal' : 'create', [TicketComment::class, $ticket]);

        $comment = DB::transaction(function () use ($request, $ticket, $isInternal) {
            $comment = $ticket->comments()->create([
                'tenant_id' => $ticket->tenant_id,
                'author_id' => $request->user()->id,
                'body' => $request->validated('body'),
                'is_internal' => $isInternal,
            ]);

            // First non-internal reply from anyone other than the ticket's
            // own client counts as the first response, for future SLA
            // reporting (Phase 5) — set once, never overwritten.
            if (! $isInternal && $ticket->first_response_at === null && $request->user()->id !== $ticket->client_id) {
                $ticket->forceFill(['first_response_at' => now()])->save();
            }

            return $comment;
        });

        return ApiResponse::success(
            data: new TicketCommentResource($comment->load('author')),
            message: 'Comment added.',
            status: 201,
        );
    }
}
