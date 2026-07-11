<?php

use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Services\Tickets\TicketPoolService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

it('lists only unassigned, Pending tickets in the pool', function () {
    [$tenant, $ticketType, $client, $agent, $pendingTicket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);

    $assignedTicket = Ticket::factory()->for($tenant)->assignedTo($agent)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
    ]);

    $poolIds = app(TicketPoolService::class)->poolQuery()->pluck('id')->all();

    expect($poolIds)->toContain($pendingTicket->id)
        ->and($poolIds)->not->toContain($assignedTicket->id);
});

it('atomically assigns a pooled ticket to the pulling agent', function () {
    [$tenant, , , $agent, $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);

    $assigned = app(TicketPoolService::class)->pull($ticket->id, $agent);

    expect($assigned->agent_id)->toBe($agent->id)
        ->and($assigned->status)->toBe(TicketStatus::InProgress);
});

it('rejects a second pull attempt on an already-claimed ticket', function () {
    [$tenant, , , $agentA, $ticket] = createTicketFixture();

    $agentB = app(TenantContext::class)->bypass(
        'test fixture setup',
        fn () => User::factory()->for($tenant)->create(),
    );

    app(TenantContext::class)->set($tenant->id);

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

    [$tenant, , , $agent, $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $agent->assignRole(RoleName::SupportAgent->value);

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

    [$tenant, , , $agent, $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $agent->assignRole(RoleName::SupportAgent->value);

    $token = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => $agent->email,
        'password' => 'password',
    ])->assertOk()->json('data.token');

    $this->withToken($token)->postJson("/api/v1/ticket-pool/{$ticket->id}/pull")->assertOk();
    $this->withToken($token)->postJson("/api/v1/ticket-pool/{$ticket->id}/pull")->assertStatus(409);
});
