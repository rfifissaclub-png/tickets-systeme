<?php

use App\Enums\RoleName;
use App\Models\TicketType;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('lets every authenticated role view the global ticket type catalog', function () {
    TicketType::factory()->create(['name' => 'Networking']);

    [$tenant, $client] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    $token = loginAs($tenant->slug, $client->email);

    $response = $this->withToken($token)->getJson('/api/v1/ticket-types')->assertOk();

    expect(collect($response->json('data'))->pluck('name'))->toContain('Networking');
});

it('lets a Super Admin create a ticket type with no tenant at all', function () {
    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $response = $this->withToken($token)->postJson('/api/v1/ticket-types', [
        'name' => 'Security Incident',
    ])->assertCreated();

    expect($response->json('data.name'))->toBe('Security Incident');

    $exists = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => TicketType::query()->where('name', 'Security Incident')->exists(),
    );
    expect($exists)->toBeTrue();
});

it('rejects a Tenant Admin creating, updating, or deleting a ticket type', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $ticketType = TicketType::factory()->create();

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->postJson('/api/v1/ticket-types', ['name' => 'New Type'])->assertForbidden();
    $this->withToken($token)->patchJson("/api/v1/ticket-types/{$ticketType->id}", ['name' => 'Renamed'])->assertForbidden();
    $this->withToken($token)->deleteJson("/api/v1/ticket-types/{$ticketType->id}")->assertForbidden();
});

it('rejects creating a ticket type with a name that already exists', function () {
    TicketType::factory()->create(['name' => 'Billing']);

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)->postJson('/api/v1/ticket-types', ['name' => 'Billing'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('lets a Super Admin rename a ticket type without tripping its own uniqueness check', function () {
    $ticketType = TicketType::factory()->create(['name' => 'Original Name']);

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)->patchJson("/api/v1/ticket-types/{$ticketType->id}", ['name' => 'Original Name'])
        ->assertOk();
});
