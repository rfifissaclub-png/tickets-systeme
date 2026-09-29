<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows a tenant user to create and list clients', function () {
    // Minimal test: create a tenant + user (uses bypass), act as that user
    [$tenant, $user] = createTenantWithUser();
    app(\App\Services\Tenancy\TenantContext::class)->set($tenant->id);

    // Ensure this tenant user has the permission expected by the policy.
    \App\Models\Permission::findOrCreate('tenants.update', 'web');
    $user->givePermissionTo('tenants.update');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/clients', ['name' => 'ACME'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'ACME');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->assertJson(fn ($json) => $json->where('data.data.0.name', 'ACME'));
});
