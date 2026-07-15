<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketCommentRequest;
use App\Http\Resources\TicketCommentResource;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Notifications\TicketCommentAddedNotification;
use App\Services\Tenancy\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketCommentController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('viewAny', [TicketComment::class, $ticket]);

        $comments = $this->tenantContext->runScoped(
            'platform-wide ticket comments lookup (super admin)',
            function () use ($request, $ticket) {
                $query = $ticket->comments()->with('author')->orderBy('created_at');

                if (! $request->user()->can('tickets.viewInternal')) {
                    $query->where('is_internal', false);
                }

                return $query->get();
            },
        );

        return ApiResponse::success(TicketCommentResource::collection($comments));
    }

    public function store(StoreTicketCommentRequest $request, Ticket $ticket): JsonResponse
    {
        $isInternal = (bool) $request->validated('is_internal', false);

        $this->authorize($isInternal ? 'createInternal' : 'create', [TicketComment::class, $ticket]);

        // Everything below touches tenant-owned rows a Super Admin has no
        // bound tenant for: the comment insert itself, the ticket update
        // (first_response_at), the client/agent relations picking a
        // notification recipient, and the final author eager-load.
        $comment = $this->tenantContext->runScoped(
            'platform-wide ticket comment creation (super admin)',
            fn () => DB::transaction(function () use ($request, $ticket, $isInternal) {
                $comment = $ticket->comments()->create([
                    'tenant_id' => $ticket->tenant_id,
                    'author_id' => $request->user()->id,
                    'body' => $request->validated('body'),
                    'is_internal' => $isInternal,
                ]);

                // First non-internal reply from anyone other than the
                // ticket's own client counts as the first response, feeding
                // the average first-response-time report — set once, never
                // overwritten.
                if (! $isInternal && $ticket->first_response_at === null && $request->user()->id !== $ticket->client_id) {
                    $ticket->forceFill(['first_response_at' => now()])->save();
                }

                // Internal notes never notify the client — notify whichever
                // side of the conversation didn't just post.
                if (! $isInternal) {
                    $recipient = $request->user()->id === $ticket->client_id ? $ticket->agent : $ticket->client;
                    $recipient?->notify(new TicketCommentAddedNotification($comment));
                }

                return $comment->load('author');
            }),
        );

        return ApiResponse::success(
            data: new TicketCommentResource($comment),
            message: __('messages.tickets.comment_added'),
            status: 201,
        );
    }
}
