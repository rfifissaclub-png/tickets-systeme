<?php

namespace App\Exceptions;

use App\Models\Ticket;
use RuntimeException;

class TicketNotQualifiedException extends RuntimeException
{
    public function __construct(public readonly Ticket $ticket)
    {
        parent::__construct(__('messages.tickets.not_qualified'));
    }
}
