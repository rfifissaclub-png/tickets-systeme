<?php

use App\Enums\RoleName;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('lets a Tenant Admin assign ticket-type skills to an agent', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $agent = User::factory()->for($tenant)->create();
    $agent->assignRole(RoleName::SupportAgent->value);
    $itSupport = TicketType::factory()->for($tenant)->create(['name' => 'IT Support']);
    $billing = TicketType::factory()->for($tenant)->create(['name' => 'Billing']);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $response = $this->withToken($token)
        ->patchJson("/api/v1/users/{$agent->id}/skills", [
            'ticket_type_ids' => [$itSupport->id, $billing->id],
        ])
        ->assertOk();

    expect(collect($response->json('data.skills'))->pluck('id')->all())
        ->toEqualCanonicalizing([$itSupport->id, $billing->id]);
});

it('rejects assigning a ticket type from another tenant as a skill', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    [$otherTenant] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $agent = User::factory()->for($tenant)->create();
    $foreignType = app(TenantContext::class)->bypass(
        'test fixture setup',
        fn () => TicketType::factory()->for($otherTenant)->create(),
    );

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)
        ->patchJson("/api/v1/users/{$agent->id}/skills", ['ticket_type_ids' => [$foreignType->id]])
        ->assertStatus(422);
});

it('lets a Manager assign skills but denies a plain Support Agent', function () {
    [$tenant, $manager] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $manager->assignRole(RoleName::Manager->value);

    $agent = User::factory()->for($tenant)->create();
    $agent->assignRole(RoleName::SupportAgent->value);
    $ticketType = TicketType::factory()->for($tenant)->create();

    $managerToken = loginAs($tenant->slug, $manager->email);
    $this->withToken($managerToken)
        ->patchJson("/api/v1/users/{$agent->id}/skills", ['ticket_type_ids' => [$ticketType->id]])
        ->assertOk();

    $otherAgent = User::factory()->for($tenant)->create();
    $otherAgent->assignRole(RoleName::SupportAgent->value);

    $agentToken = loginAs($tenant->slug, $agent->email);
    $this->withToken($agentToken)
        ->patchJson("/api/v1/users/{$otherAgent->id}/skills", ['ticket_type_ids' => [$ticketType->id]])
        ->assertForbidden();
});
