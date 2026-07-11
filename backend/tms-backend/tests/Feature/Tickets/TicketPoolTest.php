<?php

use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Exceptions\TicketNotQualifiedException;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Services\Tickets\TicketPoolService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

it('lists only unassigned, Pending tickets in the pool', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, $ticketType, $client, $agent, $pendingTicket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $agent->assignRole(RoleName::Manager->value); // tickets.assign — unfiltered pool visibility
    $agent->refresh();

    $assignedTicket = Ticket::factory()->for($tenant)->assignedTo($agent)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
    ]);

    $poolIds = app(TicketPoolService::class)->poolQuery($agent)->pluck('id')->all();

    expect($poolIds)->toContain($pendingTicket->id)
        ->and($poolIds)->not->toContain($assignedTicket->id);
});

it('only shows a skill-gated agent pool tickets matching their qualified ticket types', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, $ticketType, $client, $agent, $qualifiedTicket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $agent->assignRole(RoleName::SupportAgent->value);
    $agent->refresh();

    $otherType = TicketType::factory()->for($tenant)->create();
    $unqualifiedTicket = Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $otherType->id,
        'client_id' => $client->id,
    ]);

    // No skills assigned yet: fails closed, empty pool.
    expect(app(TicketPoolService::class)->poolQuery($agent)->pluck('id')->all())->toBe([]);

    $agent->qualifiedTicketTypes()->attach($ticketType->id, ['tenant_id' => $tenant->id]);
    $agent->refresh();

    $poolIds = app(TicketPoolService::class)->poolQuery($agent)->pluck('id')->all();

    expect($poolIds)->toContain($qualifiedTicket->id)
        ->and($poolIds)->not->toContain($unqualifiedTicket->id);
});

it('rejects pulling a ticket type the agent is not qualified for, even by direct id', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, , , $agent, $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $agent->assignRole(RoleName::SupportAgent->value);
    $agent->refresh();

    expect(fn () => app(TicketPoolService::class)->pull($ticket->id, $agent))
        ->toThrow(TicketNotQualifiedException::class);
});

it('atomically assigns a pooled ticket to the pulling agent', function () {
    [$tenant, $ticketType, , $agent, $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $agent->qualifiedTicketTypes()->attach($ticketType->id, ['tenant_id' => $tenant->id]);

    $assigned = app(TicketPoolService::class)->pull($ticket->id, $agent);

    expect($assigned->agent_id)->toBe($agent->id)
        ->and($assigned->status)->toBe(TicketStatus::InProgress);
});

it('rejects a second pull attempt on an already-claimed ticket', function () {
    [$tenant, $ticketType, , $agentA, $ticket] = createTicketFixture();

    $agentB = app(TenantContext::class)->bypass(
        'test fixture setup',
        fn () => User::factory()->for($tenant)->create(),
    );

    app(TenantContext::class)->set($tenant->id);
    $agentA->qualifiedTicketTypes()->attach($ticketType->id, ['tenant_id' => $tenant->id]);

    app(TicketPoolService::class)->pull($ticket->id, $agentA);

    // This proves the logical guard (already-assigned rejected), which is
    // what a real race resolves to once the first transaction commits and
    // the second re-reads the locked row. A single-process test can't
    // simulate two transactions racing on the SELECT ... FOR UPDATE itself
    // — that needs a genuine concurrent multi-connection test, which is a
    // manual verification step against the live stack, not this suite.
    expect(fn () => app(TicketPoolService::class)->pull($ticket->id, $agentB))
        ->toThrow(TicketAlreadyAssignedException::class);
});

it('throws when pulling a ticket id that does not exist', function () {
    [$tenant, , , $agent] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);

    expect(fn () => app(TicketPoolService::class)->pull((string) Str::uuid(), $agent))
        ->toThrow(ModelNotFoundException::class);
});

it('lets an authorized agent pull a ticket from the pool via the API', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, $ticketType, , $agent, $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $agent->assignRole(RoleName::SupportAgent->value);
    $agent->qualifiedTicketTypes()->attach($ticketType->id, ['tenant_id' => $tenant->id]);

    $login = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => $agent->email,
        'password' => 'password',
    ])->assertOk();

    $this->withToken($login->json('data.token'))
        ->postJson("/api/v1/ticket-pool/{$ticket->id}/pull")
        ->assertOk()
        ->assertJsonPath('data.agent.id', $agent->id)
        ->assertJsonPath('data.status', 'in_progress');
});

it('returns 409 for a second API pull attempt on the same ticket', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, $ticketType, , $agent, $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $agent->assignRole(RoleName::SupportAgent->value);
    $agent->qualifiedTicketTypes()->attach($ticketType->id, ['tenant_id' => $tenant->id]);

    $token = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => $agent->email,
        'password' => 'password',
    ])->assertOk()->json('data.token');

    $this->withToken($token)->postJson("/api/v1/ticket-pool/{$ticket->id}/pull")->assertOk();
    $this->withToken($token)->postJson("/api/v1/ticket-pool/{$ticket->id}/pull")->assertStatus(409);
});
