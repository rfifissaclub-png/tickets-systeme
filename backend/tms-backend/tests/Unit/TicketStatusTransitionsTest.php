<?php

use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransitionException;
use App\Services\Tickets\TicketStatusTransitions;

it('allows each transition in the ticket lifecycle', function (TicketStatus $from, TicketStatus $to) {
    expect(app(TicketStatusTransitions::class)->isAllowed($from, $to))->toBeTrue();
})->with([
    'pending to in progress' => [TicketStatus::Pending, TicketStatus::InProgress],
    'pending to on hold' => [TicketStatus::Pending, TicketStatus::OnHold],
    'in progress to on hold' => [TicketStatus::InProgress, TicketStatus::OnHold],
    'in progress to resolved' => [TicketStatus::InProgress, TicketStatus::Resolved],
    'on hold to in progress' => [TicketStatus::OnHold, TicketStatus::InProgress],
    'resolved to in progress' => [TicketStatus::Resolved, TicketStatus::InProgress],
    'resolved to closed' => [TicketStatus::Resolved, TicketStatus::Closed],
]);

it('rejects transitions outside the ticket lifecycle', function (TicketStatus $from, TicketStatus $to) {
    expect(fn () => app(TicketStatusTransitions::class)->assertAllowed($from, $to))
        ->toThrow(InvalidTicketTransitionException::class);
})->with([
    'pending to resolved' => [TicketStatus::Pending, TicketStatus::Resolved],
    'on hold to closed' => [TicketStatus::OnHold, TicketStatus::Closed],
    'closed to in progress' => [TicketStatus::Closed, TicketStatus::InProgress],
]);

it('allows a ticket to remain in its current status', function (TicketStatus $status) {
    expect(app(TicketStatusTransitions::class)->isAllowed($status, $status))->toBeTrue();
})->with(TicketStatus::cases());