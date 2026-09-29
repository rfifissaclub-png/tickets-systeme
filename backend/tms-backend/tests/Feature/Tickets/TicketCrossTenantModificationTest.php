<?php

use App\Enums\RoleName;
use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('prevents an admin from another tenant changing subject/description but allows priority change', function () {
    // Create tenant A with a ticket
    [$tenantA, $ticketType, $clientA, , $ticketA] = createTicketFixture();

    // Create tenant B and an admin there
    [$tenantB, $userB] = createTenantWithUser();
    app(TenantContext::class)->set($tenantB->id);
    $userB->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenantB->slug, $userB->email);

    // Try to change subject/description on ticketA (belongs to tenantA)
    $this->withToken($token)
        ->putJson("/api/v1/tickets/{$ticketA->id}", [
            'subject' => 'Hacked subject',
            'description' => 'Malicious edit',
        ])->assertStatus(422)
        ->assertJsonValidationErrors(['subject', 'description']);

    // But changing priority should be allowed
    $this->withToken($token)
        ->putJson("/api/v1/tickets/{$ticketA->id}", [
            'priority' => TicketPriority::High->value,
        ])->assertOk()
        ->assertJsonPath('data.priority', TicketPriority::High->value);

    // Ensure the subject remained unchanged
    $fresh = app(TenantContext::class)->bypass('test read', fn () => Ticket::find($ticketA->id));
    expect($fresh->subject)->not->toBe('Hacked subject');
});
