<?php

use App\Enums\RoleName;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('lets a Manager view users but denies a Client', function () {
    [$tenant, $manager, $client] = app(TenantContext::class)->bypass('test fixture setup', function () {
        $tenant = Tenant::factory()->create();
        $manager = User::factory()->for($tenant)->create();
        $client = User::factory()->for($tenant)->create();

        return [$tenant, $manager, $client];
    });

    // Binding tenant context also binds Spatie's team resolver (see
    // TenantContext::set), so the role *assignments* below are scoped to
    // this tenant even though the Manager/Client role definitions
    // themselves are system-wide (seeded with tenant_id IS NULL).
    app(TenantContext::class)->set($tenant->id);

    $manager->assignRole(RoleName::Manager->value);
    $client->assignRole(RoleName::Client->value);

    expect($manager->can('viewAny', User::class))->toBeTrue()
        ->and($client->can('viewAny', User::class))->toBeFalse()
        ->and($client->can('view', $manager))->toBeFalse()
        ->and($client->can('view', $client))->toBeTrue()
        ->and($manager->can('delete', $client))->toBeTrue()
        ->and($manager->can('delete', $manager))->toBeFalse();
});

it('scopes role assignments per tenant even though role definitions are system-wide', function () {
    [$tenantA, $userA] = app(TenantContext::class)->bypass('test fixture setup', function () {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        return [$tenant, $user];
    });

    [$tenantB, $userB] = app(TenantContext::class)->bypass('test fixture setup', function () {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        return [$tenant, $user];
    });

    app(TenantContext::class)->set($tenantA->id);
    $userA->assignRole(RoleName::Manager->value);

    app(TenantContext::class)->set($tenantB->id);

    expect($userB->hasRole(RoleName::Manager->value))->toBeFalse();
});

it('lets Super Admin bypass every authorization check regardless of permissions', function () {
    $superAdmin = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->superAdmin()->create());
    $superAdmin->assignRole(RoleName::SuperAdmin->value);

    [, $otherUser] = createTenantWithUser();

    expect($superAdmin->can('viewAny', User::class))->toBeTrue()
        ->and($superAdmin->can('delete', $otherUser))->toBeTrue();
});
