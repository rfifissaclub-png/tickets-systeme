<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows tenant user to create and list client contacts', function () {
    [$tenant, $user] = createTenantWithUser();
    app(\App\Services\Tenancy\TenantContext::class)->set($tenant->id);
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/clients/{$client->id}/contacts", ['name' => 'Alice', 'email' => 'alice@example.com'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Alice');

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/clients/{$client->id}/contacts")
        ->assertOk()
        ->assertJson(fn ($json) => $json->where('data.data.0.name', 'Alice'));
});
