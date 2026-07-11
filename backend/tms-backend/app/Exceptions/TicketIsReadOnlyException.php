<?php

namespace App\Exceptions;

use App\Enums\TicketStatus;
use RuntimeException;

class TicketIsReadOnlyException extends RuntimeException
{
    public function __construct(public readonly TicketStatus $status)
    {
        parent::__construct(
            $status === TicketStatus::Closed
                ? 'Closed tickets cannot be modified.'
                : 'Resolved tickets are read-only; change the status to reopen before editing.'
        );
    }
}
