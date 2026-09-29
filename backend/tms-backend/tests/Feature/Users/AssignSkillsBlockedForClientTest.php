<?php

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('forbids assigning skills to a user with the Client role', function () {
    [$tenant, $actor] = createTenantWithUser();
    app(\App\Services\Tenancy\TenantContext::class)->set($tenant->id);
    $target = User::factory()->for($tenant)->create();

    // Ensure the permission exists and actor has it
    Permission::findOrCreate('agent_skills.manage', 'web');
    $actor->givePermissionTo('agent_skills.manage');

    // Ensure the Client role exists and assign to target
    Role::findOrCreate(RoleName::Client->value, 'web');
    $target->assignRole(RoleName::Client->value);

    $ticketType = TicketType::factory()->create();

    $this->actingAs($actor, 'sanctum')
        ->patchJson("/api/v1/users/{$target->id}/skills", ['ticket_type_ids' => [$ticketType->id]])
        ->assertForbidden();
});
