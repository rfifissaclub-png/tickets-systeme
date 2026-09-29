<?php

use App\Contracts\VirusScanner;
use App\Enums\RoleName;
use App\Models\TicketAttachment;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
});

it('lets a Client create a ticket with attachments', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant,, $client] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    $ticketType = TicketType::factory()->create();
    $token = loginAs($tenant->slug, $client->email);

    // Ensure scanner allows the file through for the test.
    app()->instance(VirusScanner::class, new class implements VirusScanner
    {
        public function isInfected(string $filePath): bool
        {
            return false;
        }
    });

    $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

    $response = $this->withToken($token)
        ->post('/api/v1/tickets', [
            'ticket_type_id' => $ticketType->id,
            'tenant_id' => '',
            'client_id' => '',
            'subject' => 'Ticket with attachment',
            'description' => 'Please see attached.',
            'attachments' => [$file],
        ])
        ->assertCreated();

    expect(TicketAttachment::query()->count())->toBe(1);

    $attachment = TicketAttachment::query()->first();

    Storage::disk('s3')->assertExists($attachment->stored_path);

    $response->assertJsonPath('data.attachments.0.original_filename', 'report.pdf');
});
