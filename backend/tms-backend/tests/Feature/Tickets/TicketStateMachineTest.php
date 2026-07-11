<?php

use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransitionException;
use App\Exceptions\TicketIsReadOnlyException;
use App\Models\AuditLog;
use App\Services\Tenancy\TenantContext;
use App\Services\Tickets\TicketService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('allows a valid transition and records it in the audit trail', function () {
    [$tenant, , , , $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);

    $updated = app(TicketService::class)->transitionStatus($ticket, TicketStatus::InProgress);

    expect($updated->status)->toBe(TicketStatus::InProgress)
        ->and(
            AuditLog::query()
                ->where('action', 'ticket.status_changed')
                ->where('auditable_id', $ticket->id)
                ->exists()
        )->toBeTrue();
});

it('rejects an invalid transition', function () {
    [$tenant, , , , $ticket] = createTicketFixture(); // status: Pending

    app(TenantContext::class)->set($tenant->id);

    expect(fn () => app(TicketService::class)->transitionStatus($ticket, TicketStatus::Closed))
        ->toThrow(InvalidTicketTransitionException::class);
});

it('sets resolved_at on Resolved and clears it when reopened to InProgress', function () {
    [$tenant, , , , $ticket] = createTicketFixture(['status' => TicketStatus::InProgress]);

    app(TenantContext::class)->set($tenant->id);

    $resolved = app(TicketService::class)->transitionStatus($ticket, TicketStatus::Resolved);
    expect($resolved->resolved_at)->not->toBeNull();

    $reopened = app(TicketService::class)->transitionStatus($resolved, TicketStatus::InProgress);
    expect($reopened->resolved_at)->toBeNull();
});

it('blocks content edits on a Resolved ticket at the service layer', function () {
    [$tenant, , , , $ticket] = createTicketFixture(['status' => TicketStatus::Resolved, 'resolved_at' => now()]);

    app(TenantContext::class)->set($tenant->id);

    expect(fn () => app(TicketService::class)->update($ticket, ['subject' => 'Changed']))
        ->toThrow(TicketIsReadOnlyException::class);
});

it('blocks content edits on a Resolved ticket at the database layer, even bypassing the service', function () {
    [$tenant, , , , $ticket] = createTicketFixture(['status' => TicketStatus::Resolved, 'resolved_at' => now()]);

    app(TenantContext::class)->set($tenant->id);

    expect(fn () => DB::table('tickets')->where('id', $ticket->id)->update(['subject' => 'Sneaky change']))
        ->toThrow(QueryException::class);
});

it('still allows a Resolved ticket to transition to Closed', function () {
    [$tenant, , , , $ticket] = createTicketFixture(['status' => TicketStatus::Resolved, 'resolved_at' => now()]);

    app(TenantContext::class)->set($tenant->id);

    $closed = app(TicketService::class)->transitionStatus($ticket, TicketStatus::Closed);

    expect($closed->status)->toBe(TicketStatus::Closed)
        ->and($closed->closed_at)->not->toBeNull();
});

it('makes a Closed ticket fully immutable at the database layer', function () {
    [$tenant, , , , $ticket] = createTicketFixture(['status' => TicketStatus::Closed, 'closed_at' => now()]);

    app(TenantContext::class)->set($tenant->id);

    expect(fn () => DB::table('tickets')->where('id', $ticket->id)->update(['subject' => 'Sneaky change']))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('tickets')->where('id', $ticket->id)->update(['status' => 'in_progress']))
        ->toThrow(QueryException::class);
});

it('still allows soft-deleting a Closed ticket', function () {
    [$tenant, , , , $ticket] = createTicketFixture(['status' => TicketStatus::Closed, 'closed_at' => now()]);

    app(TenantContext::class)->set($tenant->id);

    $ticket->delete();

    expect($ticket->fresh()->trashed())->toBeTrue();
});

it('rejects assigning a read-only ticket at the service layer', function () {
    [$tenant, , , $agent, $ticket] = createTicketFixture(['status' => TicketStatus::Closed, 'closed_at' => now()]);

    app(TenantContext::class)->set($tenant->id);

    expect(fn () => app(TicketService::class)->assign($ticket, $agent))
        ->toThrow(TicketIsReadOnlyException::class);
});

it('rejects unassigning a ticket that is not currently assigned/active', function () {
    [$tenant, , , , $ticket] = createTicketFixture(); // status: Pending, already unassigned

    app(TenantContext::class)->set($tenant->id);

    expect(fn () => app(TicketService::class)->unassign($ticket))
        ->toThrow(InvalidTicketTransitionException::class);
});
