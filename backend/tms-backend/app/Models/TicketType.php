<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single platform-wide catalog, not a per-tenant resource — every tenant
 * files tickets against the same set of types (see TicketTypePolicy: only a
 * Super Admin may create/update/delete one). agent_skills is the only
 * genuinely tenant-scoped fact involving a ticket type ("this agent, in
 * this tenant, is qualified for this type"), which is why that pivot still
 * carries its own tenant_id even though this table no longer does.
 */
class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Agents qualified to pull tickets of this type from the pool.
     *
     * @return BelongsToMany<User, $this>
     */
    public function qualifiedAgents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agent_skills')->withTimestamps();
    }
}
