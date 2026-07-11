<?php

namespace App\Exceptions;

use App\Enums\TicketStatus;
use RuntimeException;

class TicketIsReadOnlyException extends RuntimeException
{
    public function __construct(public readonly TicketStatus $status)
    {
        parent::__construct(
            __($status === TicketStatus::Closed ? 'messages.tickets.read_only_closed' : 'messages.tickets.read_only_resolved')
        );
    }
}
