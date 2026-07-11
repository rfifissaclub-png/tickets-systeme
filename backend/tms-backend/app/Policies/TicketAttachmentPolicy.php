<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;

/**
 * Deliberately reuses TicketPolicy/TicketCommentPolicy rules rather than
 * defining its own: an attachment is either "part of the ticket" (viewing —
 * same rule as viewing the ticket) or "part of a reply" (uploading — same
 * rule as posting a comment, including the Closed-ticket block).
 */
class TicketAttachmentPolicy
{
    public function viewAny(User $actor, Ticket $ticket): bool
    {
        return app(TicketPolicy::class)->view($actor, $ticket);
    }

    public function create(User $actor, Ticket $ticket): bool
    {
        return app(TicketCommentPolicy::class)->create($actor, $ticket);
    }

    public function download(User $actor, TicketAttachment $attachment): bool
    {
        return app(TicketPolicy::class)->view($actor, $attachment->ticket);
    }

    public function delete(User $actor, TicketAttachment $attachment): bool
    {
        return $actor->id === $attachment->uploader_id || $actor->can('tickets.update');
    }
}
