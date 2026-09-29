<?php

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows a user with tenants.update permission to create a client', function () {
    [$tenant, $actor] = createTenantWithUser();
    app(\App\Services\Tenancy\TenantContext::class)->set($tenant->id);

    Permission::findOrCreate('tenants.update', 'web');
    $actor->givePermissionTo('tenants.update');

    $this->actingAs($actor, 'sanctum')
        ->postJson('/api/v1/clients', ['name' => 'Allowed Co'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Allowed Co');
});

it('forbids a user without tenants.update from creating a client', function () {
    [$tenant, $actor] = createTenantWithUser();
    app(\App\Services\Tenancy\TenantContext::class)->set($tenant->id);

    $this->actingAs($actor, 'sanctum')
        ->postJson('/api/v1/clients', ['name' => 'Forbidden Co'])
        ->assertStatus(403);
});
