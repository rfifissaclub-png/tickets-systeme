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
    $itSupport = TicketType::factory()->create(['name' => 'IT Support']);
    $billing = TicketType::factory()->create(['name' => 'Billing']);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $response = $this->withToken($token)
        ->patchJson("/api/v1/users/{$agent->id}/skills", [
            'ticket_type_ids' => [$itSupport->id, $billing->id],
        ])
        ->assertOk();

    expect(collect($response->json('data.skills'))->pluck('id')->all())
        ->toEqualCanonicalizing([$itSupport->id, $billing->id]);
});

it('lets two different tenants both assign the same global ticket type as a skill', function () {
    [$tenantA, $adminA] = createTenantWithUser();
    [$tenantB, $adminB] = createTenantWithUser();
    app(TenantContext::class)->set($tenantA->id);
    $adminA->assignRole(RoleName::TenantAdmin->value);
    app(TenantContext::class)->set($tenantB->id);
    $adminB->assignRole(RoleName::TenantAdmin->value);

    $sharedType = app(TenantContext::class)->bypass('test fixture setup', fn () => TicketType::factory()->create());

    $agentA = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenantA)->create());
    $agentB = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenantB)->create());

    $tokenA = loginAs($tenantA->slug, $adminA->email);
    $this->withToken($tokenA)
        ->patchJson("/api/v1/users/{$agentA->id}/skills", ['ticket_type_ids' => [$sharedType->id]])
        ->assertOk();

    $tokenB = loginAs($tenantB->slug, $adminB->email);
    $this->withToken($tokenB)
        ->patchJson("/api/v1/users/{$agentB->id}/skills", ['ticket_type_ids' => [$sharedType->id]])
        ->assertOk();
});

it('lets a Manager assign skills but denies a plain Support Agent', function () {
    [$tenant, $manager] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $manager->assignRole(RoleName::Manager->value);

    $agent = User::factory()->for($tenant)->create();
    $agent->assignRole(RoleName::SupportAgent->value);
    $ticketType = TicketType::factory()->create();

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
