<?php

namespace App\Services\Tickets;

use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransitionException;

/**
 * The ticket lifecycle graph. Enforced here at the application layer (so the
 * error is a clean 422 with a clear message) and again, for Resolved/Closed
 * specifically, at the database layer via the enforce_ticket_immutability
 * trigger — belt and suspenders, since the trigger is a backstop against any
 * write path that skips this service, not a replacement for it.
 */
class TicketStatusTransitions
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'pending' => ['in_progress', 'on_hold'],
        'in_progress' => ['on_hold', 'resolved'],
        'on_hold' => ['in_progress'],
        // Resolved is read-only content-wise but not status-wise: it can
        // still move to Closed, or be reopened back to InProgress.
        'resolved' => ['in_progress', 'closed'],
        // Terminal: enforced again by the DB trigger regardless of this list.
        'closed' => [],
    ];

    public function isAllowed(TicketStatus $from, TicketStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to->value, self::ALLOWED[$from->value], true);
    }

    public function assertAllowed(TicketStatus $from, TicketStatus $to): void
    {
        if (! $this->isAllowed($from, $to)) {
            throw new InvalidTicketTransitionException($from, $to);
        }
    }
}
