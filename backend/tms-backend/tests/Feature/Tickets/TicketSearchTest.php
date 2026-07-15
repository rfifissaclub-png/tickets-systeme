<?php

use App\Enums\RoleName;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;

it('finds a ticket by a term in the subject via full-text search', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, $ticketType, $client] = createTicketFixture(['subject' => 'Printer is on fire']);
    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
        'subject' => 'Unrelated billing question',
    ]);

    $token = loginAs($tenant->slug, $client->email);

    $response = $this->withToken($token)->getJson('/api/v1/tickets?search=printer')->assertOk();

    $subjects = collect($response->json('data'))->pluck('subject')->all();

    expect($subjects)->toContain('Printer is on fire')
        ->and($subjects)->not->toContain('Unrelated billing question');
});

it('finds a ticket by a term that only appears in the description', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, , $client, , $ticket] = createTicketFixture([
        'subject' => 'Cannot log in',
        'description' => 'Getting a segmentation fault on startup.',
    ]);
    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    $token = loginAs($tenant->slug, $client->email);

    $response = $this->withToken($token)->getJson('/api/v1/tickets?search=segmentation')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toContain($ticket->id);
});

it('filters by comma-separated status and by ticket type', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, $ticketType, $client] = createTicketFixture();
    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    $otherType = TicketType::factory()->create();

    $pending = Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
        'status' => TicketStatus::Pending,
    ]);
    $onHold = Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
        'status' => TicketStatus::OnHold,
    ]);
    $otherTypeTicket = Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $otherType->id,
        'client_id' => $client->id,
        'status' => TicketStatus::Pending,
    ]);

    $token = loginAs($tenant->slug, $client->email);

    $response = $this->withToken($token)
        ->getJson("/api/v1/tickets?status=pending,on_hold&ticket_type_id={$ticketType->id}")
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($pending->id)
        ->toContain($onHold->id)
        ->not->toContain($otherTypeTicket->id);
});

it('filters unassigned tickets', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, $ticketType, $client, $agent] = createTicketFixture(['agent_id' => null]);
    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    $unassigned = Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
        'agent_id' => null,
    ]);
    $assigned = Ticket::factory()->for($tenant)->assignedTo($agent)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
    ]);

    $token = loginAs($tenant->slug, $client->email);

    $response = $this->withToken($token)->getJson('/api/v1/tickets?unassigned=1')->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($unassigned->id)->not->toContain($assigned->id);
});

it('lets a Super Admin list tickets across every tenant', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [, , , , $ticketA] = createTicketFixture(['subject' => 'Tenant A issue']);
    [, , , , $ticketB] = createTicketFixture(['subject' => 'Tenant B issue']);

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $response = $this->withToken($token)->getJson('/api/v1/tickets')->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($ticketA->id)->toContain($ticketB->id);
});

it('scopes a Client to only their own tickets even while searching', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, $ticketType, $clientA] = createTicketFixture(['subject' => 'Cannot print reports']);
    $clientB = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenant)->create());

    app(TenantContext::class)->set($tenant->id);
    $clientA->assignRole(RoleName::Client->value);
    $clientB->assignRole(RoleName::Client->value);

    Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $clientB->id,
        'subject' => 'Cannot print invoices',
        'priority' => TicketPriority::High,
    ]);

    $token = loginAs($tenant->slug, $clientA->email);

    $response = $this->withToken($token)->getJson('/api/v1/tickets?search=print')->assertOk();

    expect(collect($response->json('data'))->pluck('subject')->all())->toBe(['Cannot print reports']);
});
