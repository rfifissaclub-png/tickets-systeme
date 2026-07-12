<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    /**
     * The cahier des charges' "Corporation Principale": a centralized,
     * always-present tenant for clients not affiliated with any specific
     * business entity, or needing global assistance. Ordinary tenant in
     * every technical respect (RLS, RBAC, tickets all work identically) —
     * only its well-known slug is special, so callers that need to route
     * an unaffiliated client somewhere have a stable id to look up rather
     * than a magic string scattered across the codebase. Seeded
     * unconditionally by DatabaseSeeder (including in production), the
     * same way RoleAndPermissionSeeder always runs.
     */
    public const string MAIN_CORPORATION_SLUG = 'main-corporation';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'settings' => 'array',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
