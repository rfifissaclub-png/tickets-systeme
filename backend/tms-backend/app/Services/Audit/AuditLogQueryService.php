<?php

namespace App\Services\Audit;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Read-only: AuditLog itself has no update/delete path (see its own
 * docblock — the audit_logs RLS policies only define SELECT/INSERT), so
 * there's nothing here but paginate().
 *
 * @phpstan-type Filters array{tenant_id?: string|null, action?: string|null, actor_id?: string|null, from?: string|null, to?: string|null}
 */
class AuditLogQueryService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  Filters  $filters
     */
    public function paginate(User $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = fn () => AuditLog::query()
            ->with('actor')
            ->when(
                $actor->hasRole(RoleName::SuperAdmin->value) && ! empty($filters['tenant_id']),
                fn ($q) => $q->where('tenant_id', $filters['tenant_id']),
            )
            ->when(! empty($filters['action']), fn ($q) => $q->where('action', $filters['action']))
            ->when(! empty($filters['actor_id']), fn ($q) => $q->where('actor_id', $filters['actor_id']))
            ->when(! empty($filters['from']), fn ($q) => $q->where('created_at', '>=', Carbon::parse($filters['from'])))
            ->when(! empty($filters['to']), fn ($q) => $q->where('created_at', '<=', Carbon::parse($filters['to'])))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        // A Tenant Admin's own bound session is already RLS-confined to
        // their tenant's rows — only a Super Admin (who has none of their
        // own) needs the audited cross-tenant bypass to see the
        // platform-wide log, exactly like TenantManagementService::paginate().
        return $actor->hasRole(RoleName::SuperAdmin->value)
            ? $this->tenantContext->bypass('platform-wide audit log listing', $query)
            : $query();
    }
}
