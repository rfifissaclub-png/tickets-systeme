<?php

namespace App\Services\Attachments;

use App\Contracts\VirusScanner;
use App\Exceptions\FileInfectedException;
use App\Exceptions\VirusScanUnavailableException;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Actual MIME-type/size validation happens in StoreTicketAttachmentRequest
 * (Laravel's `mimetypes:`/`max:` rules, checked against real file content
 * via fileinfo — not just the client-supplied extension/header) — this
 * service assumes it's already been validated and focuses on scanning and
 * storing the file safely.
 */
class FileUploadService
{
    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv',
        'application/zip',
    ];

    private const DISK = 's3';

    public function __construct(private readonly VirusScanner $scanner) {}

    /**
     * @throws FileInfectedException if the scanner positively identifies malware
     * @throws VirusScanUnavailableException if scanning is enabled, the
     *                                       scanner couldn't be reached/errored, and
     *                                       config('attachments.antivirus.fail_closed') is true
     */
    public function store(UploadedFile $file, Ticket $ticket, User $uploader): TicketAttachment
    {
        $this->scan($file);

        $extension = $file->guessExtension() ?? 'bin';

        // Never derive the storage path from the client-supplied filename —
        // a randomly generated name sidesteps path traversal, null-byte, and
        // "double extension" tricks entirely, rather than trying to sanitize
        // an attacker-controlled string. Also gives every tenant/ticket its
        // own path prefix, so a storage-level misconfiguration can't leak
        // one tenant's files under another's listing.
        $storedPath = sprintf(
            'tenants/%s/tickets/%s/%s.%s',
            $ticket->tenant_id,
            $ticket->id,
            (string) Str::uuid(),
            $extension,
        );

        $stream = fopen($file->getRealPath(), 'r');

        try {
            Storage::disk(self::DISK)->put($storedPath, $stream, ['visibility' => 'private']);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return TicketAttachment::query()->create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'uploader_id' => $uploader->id,
            'original_filename' => $this->sanitizeDisplayName($file->getClientOriginalName()),
            'disk' => self::DISK,
            'stored_path' => $storedPath,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    public function delete(TicketAttachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->stored_path);
        $attachment->delete();
    }

    /**
     * Scans the file's real (temp) path before it's ever written to
     * permanent storage — an infected file is never persisted, not
     * persisted-then-removed.
     */
    private function scan(UploadedFile $file): void
    {
        try {
            if ($this->scanner->isInfected($file->getRealPath())) {
                throw new FileInfectedException;
            }
        } catch (VirusScanUnavailableException $e) {
            if (config('attachments.antivirus.fail_closed')) {
                throw $e;
            }

            Log::warning('Virus scan unavailable; allowing upload through (fail_closed=false).', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Display-only (never used to build a path), but still stripped of
     * control characters and path separators so it can't be used for header
     * injection or a misleading path-like display name in the UI.
     */
    private function sanitizeDisplayName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '', $name) ?? $name;

        return mb_substr(trim($name), 0, 255);
    }
}
