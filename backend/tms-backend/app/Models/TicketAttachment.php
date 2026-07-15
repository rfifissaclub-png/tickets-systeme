<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Services\Tenancy\TenantContext;
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
     * Same reasoning as Ticket::resolveRouteBinding(): implicit route model
     * binding on `{attachment}` (download/destroy) has no bypass by
     * default, so a Super Admin 404s before the controller/policy ever run.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return app(TenantContext::class)->runScoped(
            'platform-wide attachment lookup (super admin)',
            fn () => parent::resolveRouteBinding($value, $field),
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
