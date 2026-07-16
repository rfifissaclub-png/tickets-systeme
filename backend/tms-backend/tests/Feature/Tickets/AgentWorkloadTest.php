<?php

use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it("lets a Tenant Admin see each agent's active ticket count", function () {
    [$tenant, $ticketType, $client, $agent, $ticket] = createTicketFixture(['agent_id' => null]);
    $admin = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenant)->create());

    app(TenantContext::class)->set($tenant->id);
    $admin->assignRole(RoleName::TenantAdmin->value);
    $agent->assignRole(RoleName::SupportAgent->value);
    $agent->qualifiedTicketTypes()->attach($ticketType->id, ['tenant_id' => $tenant->id]);
    $ticket->update(['agent_id' => $agent->id, 'status' => TicketStatus::InProgress]);

    // A Resolved ticket must not count towards the agent's active workload.
    Ticket::factory()->for($tenant)->assignedTo($agent)->withStatus(TicketStatus::Resolved)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
    ]);

    $token = loginAs($tenant->slug, $admin->email);

    $response = $this->withToken($token)->getJson('/api/v1/ticket-pool/agents')->assertOk();

    $agentRow = collect($response->json('data'))->firstWhere('id', $agent->id);
    expect($agentRow['active_ticket_count'])->toBe(1);
});

it('flags qualified vs unqualified agents when a ticket_type_id is given', function () {
    [$tenant, $ticketType, , $qualifiedAgent] = createTicketFixture();
    $admin = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenant)->create());
    $unqualifiedAgent = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenant)->create());

    app(TenantContext::class)->set($tenant->id);
    $admin->assignRole(RoleName::TenantAdmin->value);
    $qualifiedAgent->assignRole(RoleName::SupportAgent->value);
    $qualifiedAgent->qualifiedTicketTypes()->attach($ticketType->id, ['tenant_id' => $tenant->id]);
    $unqualifiedAgent->assignRole(RoleName::SupportAgent->value);

    $token = loginAs($tenant->slug, $admin->email);

    $response = $this->withToken($token)
        ->getJson("/api/v1/ticket-pool/agents?ticket_type_id={$ticketType->id}")
        ->assertOk();

    $rows = collect($response->json('data'))->keyBy('id');
    expect($rows[$qualifiedAgent->id]['qualified'])->toBeTrue();
    expect($rows[$unqualifiedAgent->id]['qualified'])->toBeFalse();
    // The Tenant Admin themselves holds tickets.assign, exempting them from
    // the skill check the same way TicketService::assign() exempts them.
    expect($rows[$admin->id]['qualified'])->toBeTrue();
});

it('denies a plain Support Agent and Client from viewing agent workload', function () {
    [$tenant, , $client, $agent] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);
    $agent->assignRole(RoleName::SupportAgent->value);

    $clientToken = loginAs($tenant->slug, $client->email);
    $this->withToken($clientToken)->getJson('/api/v1/ticket-pool/agents')->assertForbidden();

    $agentToken = loginAs($tenant->slug, $agent->email);
    $this->withToken($agentToken)->getJson('/api/v1/ticket-pool/agents')->assertForbidden();
});

it('never shows another tenant\'s agents in the workload listing', function () {
    [$tenantA, , , $agentA] = createTicketFixture();
    [$tenantB, , , $agentB] = createTicketFixture();
    $adminA = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenantA)->create());

    app(TenantContext::class)->set($tenantA->id);
    $adminA->assignRole(RoleName::TenantAdmin->value);
    $agentA->assignRole(RoleName::SupportAgent->value);

    app(TenantContext::class)->bypass('test fixture setup', function () use ($tenantB, $agentB) {
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);
        $agentB->assignRole(RoleName::SupportAgent->value);
    });

    $token = loginAs($tenantA->slug, $adminA->email);

    $response = $this->withToken($token)->getJson('/api/v1/ticket-pool/agents')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($agentA->id);
    expect($ids)->not->toContain($agentB->id);
});

it('requires a Super Admin to name a tenant, then returns that tenant\'s workload', function () {
    [$tenant, , , $agent] = createTicketFixture();

    app(TenantContext::class)->bypass('test fixture setup', function () use ($tenant, $agent) {
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $agent->assignRole(RoleName::SupportAgent->value);
    });

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)->getJson('/api/v1/ticket-pool/agents')->assertStatus(422);

    $response = $this->withToken($token)
        ->getJson("/api/v1/ticket-pool/agents?tenant_id={$tenant->id}")
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->toContain($agent->id);
});
