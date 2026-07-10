<?php

use App\Enums\RoleName;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    DB::statement('RESET app.tenant_id');
    DB::statement('RESET app.bypass_rls');
});

it('lets a Manager view users but denies a Client', function () {
    [$tenant, $manager, $client] = app(TenantContext::class)->bypass('test fixture setup', function () {
        $tenant = Tenant::factory()->create();
        $manager = User::factory()->for($tenant)->create();
        $client = User::factory()->for($tenant)->create();

        return [$tenant, $manager, $client];
    });

    // Binding tenant context also binds Spatie's team resolver (see
    // TenantContext::set), so roles created below are scoped to this tenant.
    app(TenantContext::class)->set($tenant->id);

    Role::findOrCreate(RoleName::Manager->value, 'web');
    Role::findOrCreate(RoleName::Client->value, 'web');

    $manager->assignRole(RoleName::Manager->value);
    $client->assignRole(RoleName::Client->value);

    expect($manager->can('viewAny', User::class))->toBeTrue()
        ->and($client->can('viewAny', User::class))->toBeFalse()
        ->and($client->can('view', $manager))->toBeFalse()
        ->and($client->can('view', $client))->toBeTrue()
        ->and($manager->can('delete', $client))->toBeTrue()
        ->and($manager->can('delete', $manager))->toBeFalse();
});

it('scopes roles per tenant: the same role name in another tenant does not grant access', function () {
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
    Role::findOrCreate(RoleName::Manager->value, 'web');
    $userA->assignRole(RoleName::Manager->value);

    app(TenantContext::class)->set($tenantB->id);
    Role::findOrCreate(RoleName::Manager->value, 'web');

    expect($userB->hasRole(RoleName::Manager->value))->toBeFalse();
});
