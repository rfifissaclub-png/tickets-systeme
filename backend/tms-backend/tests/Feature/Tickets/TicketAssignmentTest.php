<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Services\Tickets\TicketService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('rejects reassigning a ticket to a Support Agent who lacks the matching skill', function () {
    [$tenant, , , $agent, $ticket] = createTicketFixture(['agent_id' => null]);

    app(TenantContext::class)->set($tenant->id);
    $agent->assignRole(RoleName::SupportAgent->value);
    // Deliberately no qualifiedTicketTypes() attachment for this ticket's type.

    expect(fn () => app(TicketService::class)->assign($ticket, $agent))
        ->toThrow(ValidationException::class);

    expect($ticket->fresh()->agent_id)->toBeNull();
});

it('lets a ticket be assigned to a Support Agent who holds the matching skill', function () {
    [$tenant, $ticketType, , $agent, $ticket] = createTicketFixture(['agent_id' => null]);

    app(TenantContext::class)->set($tenant->id);
    $agent->assignRole(RoleName::SupportAgent->value);
    $agent->qualifiedTicketTypes()->attach($ticketType->id, ['tenant_id' => $tenant->id]);

    $assigned = app(TicketService::class)->assign($ticket, $agent);

    expect($assigned->agent_id)->toBe($agent->id);
});

it('exempts a Manager/Tenant Admin target from the skill check, same as the pool does for pulling', function () {
    [$tenant, , , $manager, $ticket] = createTicketFixture(['agent_id' => null]);

    app(TenantContext::class)->set($tenant->id);
    $manager->assignRole(RoleName::Manager->value);
    // No qualifiedTicketTypes() attachment at all — a Manager holds
    // tickets.assign, so TicketService::assign()'s isSkillGated exemption
    // applies the same way TicketPoolService's own does.

    $assigned = app(TicketService::class)->assign($ticket, $manager);

    expect($assigned->agent_id)->toBe($manager->id);
});

it('lets a Tenant Admin reassign an already-assigned ticket to a different qualified agent', function () {
    [$tenant, $ticketType, , $firstAgent, $ticket] = createTicketFixture();
    $secondAgent = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenant)->create());

    app(TenantContext::class)->set($tenant->id);
    $firstAgent->assignRole(RoleName::SupportAgent->value);
    $firstAgent->qualifiedTicketTypes()->attach($ticketType->id, ['tenant_id' => $tenant->id]);
    $ticket->update(['agent_id' => $firstAgent->id]);

    $secondAgent->assignRole(RoleName::SupportAgent->value);
    $secondAgent->qualifiedTicketTypes()->attach($ticketType->id, ['tenant_id' => $tenant->id]);

    $reassigned = app(TicketService::class)->assign($ticket, $secondAgent);

    expect($reassigned->agent_id)->toBe($secondAgent->id);
});
