<?php

namespace App\Exceptions;

use App\Enums\TicketStatus;
use RuntimeException;

class InvalidTicketTransitionException extends RuntimeException
{
    public function __construct(public readonly TicketStatus $from, public readonly TicketStatus $to)
    {
        parent::__construct("Cannot transition a ticket from \"{$from->value}\" to \"{$to->value}\".");
    }
}
