<?php

use App\Enums\RoleName;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;

it('isolates tickets across tenants at the database level', function () {
    [$tenantA, , , , $ticketA] = createTicketFixture();
    [, , , , $ticketB] = createTicketFixture();

    app(TenantContext::class)->set($tenantA->id);

    expect(Ticket::pluck('id')->all())->toBe([$ticketA->id])
        ->and(Ticket::find($ticketB->id))->toBeNull();
});

it('lets a Client list and view only their own tickets via the API', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, $ticketType, $clientA] = createTicketFixture();
    $clientB = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenant)->create());

    app(TenantContext::class)->set($tenant->id);
    $clientA->assignRole(RoleName::Client->value);
    $clientB->assignRole(RoleName::Client->value);

    $ticketForB = Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $clientB->id,
    ]);

    $token = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => $clientA->email,
        'password' => 'password',
    ])->assertOk()->json('data.token');

    $index = $this->withToken($token)->getJson('/api/v1/tickets')->assertOk();
    $ids = collect($index->json('data'))->pluck('id')->all();

    expect($ids)->not->toContain($ticketForB->id);

    $this->withToken($token)->getJson("/api/v1/tickets/{$ticketForB->id}")->assertForbidden();
});

it("lets an assigned agent update their own ticket but not another agent's", function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, $ticketType, $client, $agentA] = createTicketFixture();
    $agentB = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenant)->create());

    app(TenantContext::class)->set($tenant->id);
    $agentA->assignRole(RoleName::SupportAgent->value);
    $agentB->assignRole(RoleName::SupportAgent->value);

    $ticketForA = Ticket::factory()->for($tenant)->assignedTo($agentA)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
    ]);

    $tokenA = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => $agentA->email,
        'password' => 'password',
    ])->assertOk()->json('data.token');

    $tokenB = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => $agentB->email,
        'password' => 'password',
    ])->assertOk()->json('data.token');

    $this->withToken($tokenA)
        ->patchJson("/api/v1/tickets/{$ticketForA->id}", ['subject' => 'Updated by owner'])
        ->assertOk();

    $this->withToken($tokenB)
        ->patchJson("/api/v1/tickets/{$ticketForA->id}", ['subject' => 'Hijacked'])
        ->assertForbidden();
});

it('hides internal comments from the Client and blocks them from posting one', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, , $client, $agent, $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);
    $agent->assignRole(RoleName::SupportAgent->value);

    TicketComment::factory()->for($tenant)->for($ticket)->for($agent, 'author')->internal()
        ->create(['body' => 'Internal note']);
    TicketComment::factory()->for($tenant)->for($ticket)->for($agent, 'author')
        ->create(['body' => 'Public reply']);

    $clientToken = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => $client->email,
        'password' => 'password',
    ])->assertOk()->json('data.token');

    $comments = $this->withToken($clientToken)->getJson("/api/v1/tickets/{$ticket->id}/comments")->assertOk();
    $bodies = collect($comments->json('data'))->pluck('body')->all();

    expect($bodies)->toContain('Public reply')
        ->and($bodies)->not->toContain('Internal note');

    $this->withToken($clientToken)
        ->postJson("/api/v1/tickets/{$ticket->id}/comments", ['body' => 'Sneaky internal', 'is_internal' => true])
        ->assertForbidden();
});

it('lets staff with tickets.viewInternal see and post internal comments', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, , , $agent, $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $agent->assignRole(RoleName::SupportAgent->value);

    TicketComment::factory()->for($tenant)->for($ticket)->for($agent, 'author')->internal()
        ->create(['body' => 'Internal note']);

    $token = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => $agent->email,
        'password' => 'password',
    ])->assertOk()->json('data.token');

    $comments = $this->withToken($token)->getJson("/api/v1/tickets/{$ticket->id}/comments")->assertOk();
    expect(collect($comments->json('data'))->pluck('body')->all())->toContain('Internal note');

    $this->withToken($token)
        ->postJson("/api/v1/tickets/{$ticket->id}/comments", ['body' => 'Another internal note', 'is_internal' => true])
        ->assertCreated();
});
