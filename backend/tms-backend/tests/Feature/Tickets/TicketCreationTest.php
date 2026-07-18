<?php

use App\Enums\RoleName;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('lets a Tenant Admin create a ticket on behalf of a client in their own tenant', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $client = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenant)->create());
    $client->assignRole(RoleName::Client->value);

    $ticketType = TicketType::factory()->create();

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->postJson('/api/v1/tickets', [
        'ticket_type_id' => $ticketType->id,
        'client_id' => $client->id,
        'subject' => 'Cannot log in',
        'description' => 'Getting a 500 error on login.',
    ])->assertCreated()
        ->assertJsonPath('data.client.id', $client->id);
});

it('lets a Client create their own ticket, even when the form submits client_id/tenant_id as empty strings', function () {
    // TicketForm's client_id/tenant_id default to '' (not undefined) when
    // those fields are hidden for a Client — matches the real browser
    // request body, not just what's convenient to construct by hand.
    // ConvertEmptyStringsToNull turns '' into a present-but-null value
    // before validation runs; client_id's own rule must tolerate that the
    // same way tenant_id's already does (see StoreTicketRequest).
    [$tenant, , $client] = createTicketFixture();
    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    $ticketType = TicketType::factory()->create();
    $token = loginAs($tenant->slug, $client->email);

    $this->withToken($token)->postJson('/api/v1/tickets', [
        'ticket_type_id' => $ticketType->id,
        'tenant_id' => '',
        'client_id' => '',
        'subject' => 'Self-service ticket',
        'description' => 'Raised directly by the client.',
    ])->assertCreated()
        ->assertJsonPath('data.client.id', $client->id);
});

it('rejects a Super Admin creating a ticket without naming a tenant or client', function () {
    $ticketType = TicketType::factory()->create();
    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)->postJson('/api/v1/tickets', [
        'ticket_type_id' => $ticketType->id,
        'subject' => 'Platform issue',
        'description' => 'Something is broken.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['tenant_id', 'client_id']);
});

it('lets a Super Admin create a ticket for a named tenant and client', function () {
    [$tenant, , $client] = createTicketFixture();
    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    $ticketType = TicketType::factory()->create();
    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $response = $this->withToken($token)->postJson('/api/v1/tickets', [
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'ticket_type_id' => $ticketType->id,
        'subject' => 'Platform issue',
        'description' => 'Something is broken.',
    ])->assertCreated();

    expect($response->json('data.client.id'))->toBe($client->id);

    $created = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => Ticket::query()->where('subject', 'Platform issue')->first(),
    );
    expect($created->tenant_id)->toBe($tenant->id);
});

it('rejects a Super Admin naming a client that does not belong to the named tenant', function () {
    [$tenantA, , $clientA] = createTicketFixture();
    [$tenantB] = createTicketFixture();
    app(TenantContext::class)->set($tenantA->id);
    $clientA->assignRole(RoleName::Client->value);

    $ticketType = TicketType::factory()->create();
    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)->postJson('/api/v1/tickets', [
        'tenant_id' => $tenantB->id,
        'client_id' => $clientA->id,
        'ticket_type_id' => $ticketType->id,
        'subject' => 'Mismatched',
        'description' => 'Client belongs to a different tenant.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('client_id');
});
