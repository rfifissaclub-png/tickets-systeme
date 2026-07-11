<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\TicketAttachmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class TicketAttachment extends Model
{
    /** @use HasFactory<TicketAttachmentFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'uploader_id',
        'original_filename',
        'disk',
        'stored_path',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function temporaryDownloadUrl(int $expiresInMinutes = 5): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $this->stored_path,
            now()->addMinutes($expiresInMinutes),
            ['ResponseContentDisposition' => 'attachment; filename="'.addslashes($this->original_filename).'"'],
        );
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
}
