<?php

use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
});

it('lets a ticket participant upload a valid attachment', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, , $client, , $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    $token = loginAs($tenant->slug, $client->email);

    $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

    $response = $this->withToken($token)
        ->post("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file])
        ->assertCreated();

    $attachment = TicketAttachment::query()->first();

    expect($attachment)->not->toBeNull()
        ->and($attachment->original_filename)->toBe('report.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($response->json('data.original_filename'))->toBe('report.pdf');

    Storage::disk('s3')->assertExists($attachment->stored_path);
    // The storage key is never derived from the client filename.
    expect($attachment->stored_path)->not->toContain('report.pdf');
});

it('rejects a disallowed MIME type', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, , $client, , $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    $token = loginAs($tenant->slug, $client->email);

    $file = UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload');

    $this->withToken($token)
        ->post("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file])
        ->assertStatus(422);
});

it('rejects an oversized file', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, , $client, , $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    $token = loginAs($tenant->slug, $client->email);

    $file = UploadedFile::fake()->create('huge.pdf', 26 * 1024, 'application/pdf');

    $this->withToken($token)
        ->post("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file])
        ->assertStatus(422);
});

it('blocks uploading to a Closed ticket', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, , $client, , $ticket] = createTicketFixture(['status' => TicketStatus::Closed, 'closed_at' => now()]);

    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    $token = loginAs($tenant->slug, $client->email);

    $file = UploadedFile::fake()->create('note.pdf', 10, 'application/pdf');

    $this->withToken($token)
        ->post("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file])
        ->assertForbidden();
});

it("denies downloading another client's ticket attachment", function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, $ticketType, $clientA] = createTicketFixture();
    $clientB = app(TenantContext::class)->bypass(
        'test fixture setup',
        fn () => User::factory()->for($tenant)->create(),
    );

    app(TenantContext::class)->set($tenant->id);
    $clientA->assignRole(RoleName::Client->value);
    $clientB->assignRole(RoleName::Client->value);

    $ticketForB = Ticket::factory()->for($tenant)->create([
        'ticket_type_id' => $ticketType->id,
        'client_id' => $clientB->id,
    ]);

    $attachment = TicketAttachment::factory()->for($tenant)->for($ticketForB)->create([
        'uploader_id' => $clientB->id,
    ]);

    $tokenA = loginAs($tenant->slug, $clientA->email);

    $this->withToken($tokenA)
        ->getJson("/api/v1/attachments/{$attachment->id}/download")
        ->assertForbidden();
});

it('lets the uploader delete their own attachment but not another agent', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, , , $agentA, $ticket] = createTicketFixture(['agent_id' => null]);
    $agentB = app(TenantContext::class)->bypass(
        'test fixture setup',
        fn () => User::factory()->for($tenant)->create(),
    );

    app(TenantContext::class)->set($tenant->id);
    $agentA->assignRole(RoleName::SupportAgent->value);
    $agentB->assignRole(RoleName::SupportAgent->value);

    $attachment = TicketAttachment::factory()->for($tenant)->for($ticket)->create([
        'uploader_id' => $agentA->id,
    ]);

    $tokenB = loginAs($tenant->slug, $agentB->email);

    $this->withToken($tokenB)
        ->deleteJson("/api/v1/attachments/{$attachment->id}")
        ->assertForbidden();

    $tokenA = loginAs($tenant->slug, $agentA->email);

    $this->withToken($tokenA)
        ->deleteJson("/api/v1/attachments/{$attachment->id}")
        ->assertOk();

    expect(TicketAttachment::query()->find($attachment->id))->toBeNull();
});
