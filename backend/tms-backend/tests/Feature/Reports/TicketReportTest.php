<?php

use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Reports\TicketReportService;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Carbon;

it('calculates average resolution and first-response time correctly', function () {
    [$tenant, $ticketType, $client, $agent] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);

    // Exact, known durations — one row makes the average trivially the
    // value itself, so this proves the EXTRACT/AVG math is right rather
    // than just "some number came back".
    Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
        'agent_id' => $agent->id,
        'status' => TicketStatus::Resolved,
        'created_at' => Carbon::parse('2026-01-01 00:00:00'),
        'first_response_at' => Carbon::parse('2026-01-01 01:00:00'),
        'resolved_at' => Carbon::parse('2026-01-01 05:00:00'),
    ]);

    $overview = app(TicketReportService::class)->overview();

    expect($overview['avg_resolution_seconds'])->toBe(5 * 3600.0)
        ->and($overview['avg_first_response_seconds'])->toBe(1 * 3600.0);
});

it('counts tickets by status and priority in the overview', function () {
    [$tenant, $ticketType, $client] = createTicketFixture(['status' => TicketStatus::Pending]);

    app(TenantContext::class)->set($tenant->id);

    Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
        'status' => TicketStatus::Pending,
    ]);
    Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
        'status' => TicketStatus::Closed,
        'resolved_at' => now(),
        'closed_at' => now(),
    ]);

    $overview = app(TicketReportService::class)->overview();

    expect($overview['total_tickets'])->toBe(3)
        ->and((int) $overview['by_status']['pending'])->toBe(2)
        ->and((int) $overview['by_status']['closed'])->toBe(1);
});

it('aggregates ticket counts by agent', function () {
    [$tenant, $ticketType, $client, $agentA] = createTicketFixture(['agent_id' => null]);
    $agentB = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenant)->create());

    app(TenantContext::class)->set($tenant->id);

    Ticket::factory()->for($tenant)->assignedTo($agentA)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
    ]);
    Ticket::factory()->for($tenant)->assignedTo($agentA)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
    ]);
    Ticket::factory()->for($tenant)->assignedTo($agentB)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
    ]);

    $byAgent = app(TicketReportService::class)->byAgent()->keyBy('agent_id');

    expect((int) $byAgent[$agentA->id]->ticket_count)->toBe(2)
        ->and((int) $byAgent[$agentB->id]->ticket_count)->toBe(1);
});

it('denies report access to a Support Agent', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, , , $agent] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $agent->assignRole(RoleName::SupportAgent->value);

    $token = loginAs($tenant->slug, $agent->email);

    $this->withToken($token)->getJson('/api/v1/reports/overview')->assertForbidden();
});

it('denies the platform-wide tenant report to a Tenant Admin', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, , , $admin] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $admin->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenant->slug, $admin->email);

    $this->withToken($token)->getJson('/api/v1/reports/tickets-by-tenant')->assertForbidden();
});

it('lets a Super Admin see ticket counts across all tenants', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenantA, $ticketTypeA, $clientA] = createTicketFixture();
    [$tenantB, $ticketTypeB, $clientB] = createTicketFixture();

    app(TenantContext::class)->set($tenantA->id);
    Ticket::factory()->for($tenantA)->create(['ticket_type_id' => $ticketTypeA->id, 'client_id' => $clientA->id]);

    app(TenantContext::class)->set($tenantB->id);
    Ticket::factory()->for($tenantB)->create(['ticket_type_id' => $ticketTypeB->id, 'client_id' => $clientB->id]);

    $superAdmin = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->superAdmin()->create());
    app(TenantContext::class)->bindPlatformTeam();
    $superAdmin->assignRole(RoleName::SuperAdmin->value);

    $token = loginAs('', $superAdmin->email);

    $response = $this->withToken($token)->getJson('/api/v1/reports/tickets-by-tenant')->assertOk();

    $byTenant = collect($response->json('data'))->keyBy('tenant_id');

    expect((int) $byTenant[$tenantA->id]['ticket_count'])->toBeGreaterThanOrEqual(2)
        ->and((int) $byTenant[$tenantB->id]['ticket_count'])->toBeGreaterThanOrEqual(2);
});

it('lets a Super Admin view the overview, by-agent, and trends reports aggregated across every tenant', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenantA, $ticketTypeA, $clientA, $agentA] = createTicketFixture();
    [$tenantB, $ticketTypeB, $clientB, $agentB] = createTicketFixture();

    // Deliberately bypass() rather than set() for both: TenantContext is a
    // container singleton, so unlike two real, separate requests, a set()
    // call here would leak into this same test's later Super Admin
    // request below and make it look tenant-bound when it must not be.
    app(TenantContext::class)->bypass(
        'test fixture setup',
        fn () => Ticket::factory()->for($tenantA)->assignedTo($agentA)->create(['ticket_type_id' => $ticketTypeA->id, 'client_id' => $clientA->id]),
    );
    app(TenantContext::class)->bypass(
        'test fixture setup',
        fn () => Ticket::factory()->for($tenantB)->assignedTo($agentB)->create(['ticket_type_id' => $ticketTypeB->id, 'client_id' => $clientB->id]),
    );

    $superAdmin = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->superAdmin()->create());
    app(TenantContext::class)->bindPlatformTeam();
    $superAdmin->assignRole(RoleName::SuperAdmin->value);

    $token = loginAs('', $superAdmin->email);

    // Before this fix, all three of these threw "No tenant is bound to
    // the current request context" for a Super Admin — requireTenant() had
    // no branch for the one role that genuinely has none.
    $overview = $this->withToken($token)->getJson('/api/v1/reports/overview')->assertOk();
    expect($overview->json('data.total_tickets'))->toBeGreaterThanOrEqual(2);

    $byAgent = $this->withToken($token)->getJson('/api/v1/reports/tickets-by-agent')->assertOk();
    expect(collect($byAgent->json('data'))->pluck('agent_id'))
        ->toContain($agentA->id)
        ->toContain($agentB->id);

    $this->withToken($token)->getJson('/api/v1/reports/ticket-trends')->assertOk();
});

it('buckets ticket trends by day', function () {
    [$tenant, $ticketType, $client] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);

    $day1 = Carbon::parse('2026-02-01 10:00:00');
    $day2 = Carbon::parse('2026-02-02 10:00:00');

    Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
        'created_at' => $day1,
    ]);
    Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
        'created_at' => $day2,
    ]);
    Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
        'created_at' => $day2,
    ]);

    $trends = app(TicketReportService::class)
        ->trends(Carbon::parse('2026-02-01'), Carbon::parse('2026-02-02'))
        ->keyBy('date');

    expect($trends['2026-02-01']['created'])->toBe(1)
        ->and($trends['2026-02-02']['created'])->toBe(2);
});
