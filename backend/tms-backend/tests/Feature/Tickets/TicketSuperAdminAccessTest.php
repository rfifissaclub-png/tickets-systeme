<?php

use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * A Super Admin has no tenant of their own — every one of these previously
 * either 404'd (implicit route model binding on {ticket}/{attachment} has
 * no bypass) or silently no-op'd/emptied out (a relation loaded, or a row
 * written, after whatever bypass the call needed had already ended). See
 * AppServiceProvider::configureCrossTenantRouteBindings() and the
 * TenantContext::runScoped() calls across the ticket controllers.
 */

/**
 * TenantContext::set() would work for assignRole()'s own team-scoping need,
 * but it's a container singleton whose $tenantId would then leak into this
 * same test's later, deliberately-unbound Super Admin request — silently
 * turning the "no tenant bound" scenario these tests exist to cover into an
 * accidentally-bound one. Scopes only Spatie's own team resolver instead
 * (matching UserManagementService::withTeamContext()'s production pattern),
 * leaving TenantContext's own state untouched.
 */
function assignRoleWithinTenant(User $user, string $role, string $tenantId): void
{
    $registrar = app(PermissionRegistrar::class);
    $previous = $registrar->getPermissionsTeamId();
    $registrar->setPermissionsTeamId($tenantId);

    try {
        $user->assignRole($role);
    } finally {
        $registrar->setPermissionsTeamId($previous);
    }
}
beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('s3');
});

it('lets a Super Admin view, update, and delete a ticket in any tenant', function () {
    [$tenant, , $client, $agent, $ticket] = createTicketFixture();

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonPath('data.client.id', $client->id);

    $this->withToken($token)->patchJson("/api/v1/tickets/{$ticket->id}", ['subject' => 'Updated by Super Admin'])
        ->assertOk()
        ->assertJsonPath('data.subject', 'Updated by Super Admin');

    $this->withToken($token)->deleteJson("/api/v1/tickets/{$ticket->id}")->assertOk();

    $trashed = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => \App\Models\Ticket::withTrashed()->find($ticket->id)?->trashed(),
    );
    expect($trashed)->toBeTrue();
});

it('lets a Super Admin transition a ticket\'s status in any tenant', function () {
    [, , , , $ticket] = createTicketFixture(['status' => TicketStatus::Pending, 'agent_id' => null]);

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');
});

it('lets a Super Admin assign a ticket to an agent belonging to the same tenant', function () {
    [$tenant, $ticketType, , , $ticket] = createTicketFixture(['agent_id' => null]);

    $agent = app(TenantContext::class)->bypass('test fixture setup', function () use ($tenant, $ticketType) {
        $agent = User::factory()->for($tenant)->create();
        assignRoleWithinTenant($agent, RoleName::SupportAgent->value, $tenant->id);
        // A plain Support Agent is skill-gated by TicketService::assign()
        // too, not just the pool — qualify them for this ticket's type.
        $agent->qualifiedTicketTypes()->attach($ticketType->id, ['tenant_id' => $tenant->id]);

        return $agent;
    });

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)->patchJson("/api/v1/tickets/{$ticket->id}/assign", ['agent_id' => $agent->id])
        ->assertOk()
        ->assertJsonPath('data.agent.id', $agent->id);
});

it('rejects a Super Admin assigning a ticket to an agent from a different tenant', function () {
    [, , , , $ticket] = createTicketFixture(['agent_id' => null]);
    [$otherTenant] = createTicketFixture();

    $foreignAgent = app(TenantContext::class)->bypass('test fixture setup', function () use ($otherTenant) {
        $agent = User::factory()->for($otherTenant)->create();
        assignRoleWithinTenant($agent, RoleName::SupportAgent->value, $otherTenant->id);

        return $agent;
    });

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)->patchJson("/api/v1/tickets/{$ticket->id}/assign", ['agent_id' => $foreignAgent->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('agent_id');
});

it('lets a Super Admin list and post comments on a ticket in any tenant', function () {
    [, , , , $ticket] = createTicketFixture();

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)->getJson("/api/v1/tickets/{$ticket->id}/comments")->assertOk();

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->id}/comments", ['body' => 'Checking in from the platform team.'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Checking in from the platform team.');

    $exists = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => TicketComment::query()->where('ticket_id', $ticket->id)->where('body', 'Checking in from the platform team.')->exists(),
    );
    expect($exists)->toBeTrue();
});

it('lets a Super Admin list, upload, download, and delete an attachment on a ticket in any tenant', function () {
    [, , , , $ticket] = createTicketFixture();

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)->getJson("/api/v1/tickets/{$ticket->id}/attachments")->assertOk();

    $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

    $uploadResponse = $this->withToken($token)
        ->post("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file])
        ->assertCreated();

    $attachmentId = $uploadResponse->json('data.id');

    $attachment = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => TicketAttachment::query()->find($attachmentId),
    );
    expect($attachment)->not->toBeNull();
    Storage::disk('s3')->assertExists($attachment->stored_path);

    $this->withToken($token)->getJson("/api/v1/attachments/{$attachmentId}/download")
        ->assertOk()
        ->assertJsonStructure(['data' => ['url']]);

    $this->withToken($token)->deleteJson("/api/v1/attachments/{$attachmentId}")->assertOk();

    Storage::disk('s3')->assertMissing($attachment->stored_path);
});
