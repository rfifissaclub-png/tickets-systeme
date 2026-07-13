<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('lets a Tenant Admin create a user with roles inside their own tenant', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $response = $this->withToken($token)->postJson('/api/v1/users', [
        'name' => 'New Agent',
        'email' => 'new-agent@acme.tms.local',
        'password' => 'a-strong-password',
        'roles' => [RoleName::SupportAgent->value],
    ])->assertCreated();

    expect($response->json('data.email'))->toBe('new-agent@acme.tms.local')
        ->and($response->json('data.roles'))->toBe([RoleName::SupportAgent->value]);

    $created = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => User::query()->where('email', 'new-agent@acme.tms.local')->first(),
    );
    expect($created->tenant_id)->toBe($tenant->id);
});

it('forces a new user into the Tenant Admin\'s own tenant regardless of a tenant_id in the request', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    [$otherTenant] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $response = $this->withToken($token)->postJson('/api/v1/users', [
        'tenant_id' => $otherTenant->id,
        'name' => 'Sneaky',
        'email' => 'sneaky@acme.tms.local',
        'password' => 'a-strong-password',
    ])->assertCreated();

    expect($response->json('data.tenant_id'))->toBe($tenant->id);
});

it('rejects a Tenant Admin granting the Super Admin role', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->postJson('/api/v1/users', [
        'name' => 'Wannabe Super Admin',
        'email' => 'wannabe@acme.tms.local',
        'password' => 'a-strong-password',
        'roles' => [RoleName::SuperAdmin->value],
    ])->assertStatus(422);
});

it('rejects creating a user with more than one role at once', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->postJson('/api/v1/users', [
        'name' => 'Double Role',
        'email' => 'double-role@acme.tms.local',
        'password' => 'a-strong-password',
        'roles' => [RoleName::Manager->value, RoleName::SupportAgent->value],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('roles');
});

it('rejects assigning more than one role to an existing user at once', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $agent = User::factory()->for($tenant)->create();
    $agent->assignRole(RoleName::SupportAgent->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->patchJson("/api/v1/users/{$agent->id}/roles", [
        'roles' => [RoleName::Manager->value, RoleName::SupportAgent->value],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('roles');
});

it('rejects a duplicate email within the same tenant but allows it across two different tenants', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);
    $existing = User::factory()->for($tenant)->create(['email' => 'shared@example.com']);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->postJson('/api/v1/users', [
        'name' => 'Duplicate',
        'email' => $existing->email,
        'password' => 'a-strong-password',
    ])->assertStatus(422)->assertJsonPath('errors.email.0', fn ($message) => is_string($message));

    // A Super Admin creating a user with the same email in a *different*
    // tenant must succeed: uniqueness is scoped per tenant, not global.
    $superAdmin = createSuperAdmin();
    [$otherTenant] = createTenantWithUser();
    $superAdminToken = loginAs('', $superAdmin->email);

    $this->withToken($superAdminToken)->postJson('/api/v1/users', [
        'tenant_id' => $otherTenant->id,
        'name' => 'Same Email Different Tenant',
        'email' => $existing->email,
        'password' => 'a-strong-password',
    ])->assertCreated();
});

it('lets a Tenant Admin update roles for a user in their tenant but not for themselves', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);
    $member = User::factory()->for($tenant)->create();
    $member->assignRole(RoleName::SupportAgent->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)
        ->patchJson("/api/v1/users/{$member->id}/roles", ['roles' => [RoleName::Manager->value]])
        ->assertOk()
        ->assertJsonPath('data.roles', [RoleName::Manager->value]);

    // Self-role-assignment is blocked even for a Tenant Admin — see
    // UserPolicy::manageRoles().
    $this->withToken($token)
        ->patchJson("/api/v1/users/{$tenantAdmin->id}/roles", ['roles' => [RoleName::Manager->value]])
        ->assertForbidden();
});

it('prevents a Tenant Admin from managing users in another tenant', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    [$otherTenant, $otherUser] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->getJson("/api/v1/users/{$otherUser->id}")->assertNotFound();
});
