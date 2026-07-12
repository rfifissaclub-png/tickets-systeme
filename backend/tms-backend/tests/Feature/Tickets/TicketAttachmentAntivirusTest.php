<?php

use App\Contracts\VirusScanner;
use App\Enums\RoleName;
use App\Exceptions\VirusScanUnavailableException;
use App\Models\TicketAttachment;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Exercises FileUploadService's scanning behavior against fake VirusScanner
 * implementations bound over the real container binding (see
 * AppServiceProvider::register()) — no real clamd needed for these; the
 * ClamAvScanner <-> real clamd wire protocol itself was verified manually
 * against a live daemon, not re-proven here.
 */
beforeEach(function () {
    Storage::fake('s3');
});

it('rejects an infected file and never persists it to storage', function () {
    app()->instance(VirusScanner::class, new class implements VirusScanner
    {
        public function isInfected(string $filePath): bool
        {
            return true;
        }
    });

    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, , $client, , $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    $token = loginAs($tenant->slug, $client->email);

    $file = UploadedFile::fake()->create('eicar.txt', 1, 'text/plain');

    $this->withToken($token)
        ->post("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file])
        ->assertStatus(422)
        ->assertJsonPath('code', 'file_infected');

    expect(TicketAttachment::query()->count())->toBe(0);
    Storage::disk('s3')->assertDirectoryEmpty("tenants/{$tenant->id}");
});

it('rejects the upload when the scanner is unreachable and fail_closed is true', function () {
    config(['attachments.antivirus.fail_closed' => true]);

    app()->instance(VirusScanner::class, new class implements VirusScanner
    {
        public function isInfected(string $filePath): bool
        {
            throw new VirusScanUnavailableException('clamd connection refused');
        }
    });

    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, , $client, , $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    $token = loginAs($tenant->slug, $client->email);

    $file = UploadedFile::fake()->create('report.pdf', 10, 'application/pdf');

    $this->withToken($token)
        ->post("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file])
        ->assertStatus(503)
        ->assertJsonPath('code', 'virus_scan_unavailable');

    expect(TicketAttachment::query()->count())->toBe(0);
});

it('allows the upload through when the scanner is unreachable and fail_closed is false', function () {
    config(['attachments.antivirus.fail_closed' => false]);

    app()->instance(VirusScanner::class, new class implements VirusScanner
    {
        public function isInfected(string $filePath): bool
        {
            throw new VirusScanUnavailableException('clamd connection refused');
        }
    });

    $this->seed(RoleAndPermissionSeeder::class);

    [$tenant, , $client, , $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);

    $token = loginAs($tenant->slug, $client->email);

    $file = UploadedFile::fake()->create('report.pdf', 10, 'application/pdf');

    $this->withToken($token)
        ->post("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file])
        ->assertCreated();

    expect(TicketAttachment::query()->count())->toBe(1);
});
