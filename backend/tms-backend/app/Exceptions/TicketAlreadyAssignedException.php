<?php

namespace App\Exceptions;

use App\Models\Ticket;
use RuntimeException;

class TicketAlreadyAssignedException extends RuntimeException
{
    public function __construct(public readonly Ticket $ticket)
    {
        parent::__construct('This ticket has already been claimed by another agent.');
    }
}
