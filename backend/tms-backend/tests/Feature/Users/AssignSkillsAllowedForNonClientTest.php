<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows assigning skills to a non-client user when actor has permission', function () {
    [$tenant, $actor] = createTenantWithUser();
    app(\App\Services\Tenancy\TenantContext::class)->set($tenant->id);
    $target = User::factory()->for($tenant)->create();

    Permission::findOrCreate('agent_skills.manage', 'web');
    $actor->givePermissionTo('agent_skills.manage');

    $ticketType = TicketType::factory()->create();

    $this->actingAs($actor, 'sanctum')
        ->patchJson("/api/v1/users/{$target->id}/skills", ['ticket_type_ids' => [$ticketType->id]])
        ->assertOk();
});
