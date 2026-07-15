<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Services\Tenancy\TenantContext;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'ticket_type_id',
        'client_id',
        'agent_id',
        'subject',
        'description',
        'status',
        'priority',
        'first_response_at',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * In the pool: unassigned and waiting to be pulled by an agent.
     */
    public function isInPool(): bool
    {
        return $this->agent_id === null && $this->status === TicketStatus::Pending;
    }

    public function isReadOnly(): bool
    {
        return $this->status === TicketStatus::Resolved || $this->status === TicketStatus::Closed;
    }

    /**
     * Every route with a `Ticket $ticket` parameter (show/update/destroy,
     * status, assign, comments, attachments) relies on Laravel's implicit
     * route model binding, which by default resolves with a plain
     * `$query->first()` — no bypass, so a Super Admin (no tenant bound, see
     * ResolveTenantContext) 404s before the controller, let alone the
     * policy, ever runs. Overriding this (rather than a global
     * `Route::bind('ticket', ...)`) keeps the fix scoped to genuine
     * implicit *model* binding specifically: TicketPoolController::pull()'s
     * `{ticket}` route parameter is typed `string`, not `Ticket` (it does
     * its own locked lookup instead — see that controller's own docblock),
     * so it never reaches this method at all, unlike a name-keyed
     * Route::bind() which would have applied regardless of that method's
     * own parameter type and broken it.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return app(TenantContext::class)->runScoped(
            'platform-wide ticket lookup (super admin)',
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
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * @return HasMany<TicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }
}
