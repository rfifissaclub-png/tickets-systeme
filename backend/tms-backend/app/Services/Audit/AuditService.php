<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    private const REDACTED_KEYS = ['password', 'remember_token'];

    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        // Auth::id() is null for every pre-Sanctum-authentication flow
        // (login itself, password reset, email verification via a signed
        // link) — none of them go through auth:sanctum, so there's no
        // resolved guard principal yet even though the acted-upon user is
        // right there as $auditable. Those call sites must pass the actor
        // id explicitly; every other caller runs inside an authenticated
        // request and can rely on the default.
        ?string $actorId = null,
    ): void {
        AuditLog::query()->create([
            'tenant_id' => $this->resolveTenantId($auditable),
            'actor_id' => $actorId ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $this->redact($oldValues),
            'new_values' => $this->redact($newValues),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Prefer the tenant the audited model actually belongs to over the
     * request's bound tenant context: they usually match, but during a
     * bypass (e.g. Super Admin provisioning a new tenant, or a pre-auth
     * login lookup) the acting context has no tenant while the model does.
     */
    private function resolveTenantId(?Model $auditable): ?string
    {
        if ($auditable instanceof Tenant) {
            return $auditable->getKey();
        }

        if ($auditable instanceof User) {
            return $auditable->tenant_id;
        }

        if ($auditable !== null && array_key_exists('tenant_id', $auditable->getAttributes())) {
            return $auditable->getAttribute('tenant_id');
        }

        return $this->tenantContext->id();
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return array_diff_key($values, array_flip(self::REDACTED_KEYS));
    }
}
