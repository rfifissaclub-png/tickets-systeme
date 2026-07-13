<?php

use App\Enums\RoleName;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('lets a Super Admin create a new tenant', function () {
    $superAdmin = createSuperAdmin();

    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)->postJson('/api/v1/tenants', [
        'name' => 'Globex Corporation',
        'slug' => 'globex',
    ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'globex');

    $exists = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => Tenant::query()->where('slug', 'globex')->exists(),
    );

    expect($exists)->toBeTrue();
});

it('rejects a Tenant Admin creating a new tenant', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->postJson('/api/v1/tenants', [
        'name' => 'Initech',
        'slug' => 'initech',
    ])->assertForbidden();
});

it('rejects creating a tenant with a slug that already exists', function () {
    $superAdmin = createSuperAdmin();
    app(TenantContext::class)->bypass('test fixture setup', fn () => Tenant::factory()->create(['slug' => 'acme']));

    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)->postJson('/api/v1/tenants', [
        'name' => 'Acme Duplicate',
        'slug' => 'acme',
    ])->assertStatus(422)->assertJsonPath('errors.slug.0', fn ($message) => is_string($message));
});

it('lets a Super Admin list every tenant across the platform', function () {
    $superAdmin = createSuperAdmin();
    app(TenantContext::class)->bypass('test fixture setup', fn () => Tenant::factory()->count(2)->create());

    $token = loginAs('', $superAdmin->email);

    $response = $this->withToken($token)->getJson('/api/v1/tenants')->assertOk();

    expect(count($response->json('data')))->toBeGreaterThanOrEqual(2);
});

it('lets a Tenant Admin view and update their own tenant but not another tenant', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $otherTenant = app(TenantContext::class)->bypass('test fixture setup', fn () => Tenant::factory()->create());

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->getJson("/api/v1/tenants/{$tenant->id}")->assertOk();

    $this->withToken($token)
        ->patchJson("/api/v1/tenants/{$tenant->id}", ['name' => 'Acme Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme Renamed');

    // A different tenant's row simply doesn't exist as far as this Tenant
    // Admin's RLS-bound session is concerned — 404, not 403, same as every
    // other cross-tenant lookup in this app.
    $this->withToken($token)->getJson("/api/v1/tenants/{$otherTenant->id}")->assertNotFound();
});

it('only a Super Admin may suspend (soft delete) a tenant', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $tenantAdminToken = loginAs($tenant->slug, $tenantAdmin->email);
    $this->withToken($tenantAdminToken)->deleteJson("/api/v1/tenants/{$tenant->id}")->assertForbidden();

    $superAdmin = createSuperAdmin();
    $superAdminToken = loginAs('', $superAdmin->email);
    $this->withToken($superAdminToken)->deleteJson("/api/v1/tenants/{$tenant->id}")->assertOk();

    $trashed = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => Tenant::withTrashed()->find($tenant->id)->trashed(),
    );

    expect($trashed)->toBeTrue();
});

it('moves a suspended tenant\'s clients to Main Corporation but locks its staff out', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $mainCorporation = app(TenantContext::class)->bypass(
        'test fixture setup',
        fn () => Tenant::factory()->create(['slug' => Tenant::MAIN_CORPORATION_SLUG]),
    );

    $client = User::factory()->for($tenant)->create();
    $client->assignRole(RoleName::Client->value);

    $agent = User::factory()->for($tenant)->create();
    $agent->assignRole(RoleName::SupportAgent->value);

    $superAdmin = createSuperAdmin();
    $superAdminToken = loginAs('', $superAdmin->email);
    $this->withToken($superAdminToken)->deleteJson("/api/v1/tenants/{$tenant->id}")->assertOk();

    [$client, $agent, $tenantAdmin] = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => [$client->fresh(), $agent->fresh(), $tenantAdmin->fresh()],
    );

    expect($client->tenant_id)->toBe($mainCorporation->id)
        ->and($agent->tenant_id)->toBe($tenant->id)
        ->and($tenantAdmin->tenant_id)->toBe($tenant->id);

    // The client can still sign in — now under Main Corporation — while the
    // suspended tenant's own staff can no longer resolve their tenant at all.
    $this->postJson('/api/v1/auth/login', [
        'tenant' => $mainCorporation->slug,
        'email' => $client->email,
        'password' => 'password',
    ])->assertOk();

    $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => $agent->email,
        'password' => 'password',
    ])->assertStatus(422);
});
