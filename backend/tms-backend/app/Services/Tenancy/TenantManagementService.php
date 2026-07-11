<?php

namespace App\Services\Tenancy;

use App\Enums\RoleName;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * paginate()/create()/delete() are only ever reachable by a Super Admin
 * (see TenantPolicy — viewAny/create/delete are hardcoded to that role, not
 * permission-based), so they bypass unconditionally. find()/update() are
 * also reachable by a Tenant Admin viewing/editing their own tenant, where
 * bypassing unconditionally would be a real hole: it would lift the RLS
 * confinement that's the *only* thing stopping a Tenant Admin's
 * permission-based `tenants.view`/`tenants.update` check (which has no
 * tenant-identity check of its own — see TenantPolicy::view()) from
 * reaching a tenant that isn't theirs. Bypass only when the actor is
 * actually a Super Admin, exactly like UserManagementService.
 */
class TenantManagementService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->tenantContext->bypass(
            'platform-wide tenant listing',
            fn () => Tenant::query()->orderBy('name')->paginate($perPage),
        );
    }

    public function find(User $actor, string $tenantId): Tenant
    {
        if (! $actor->hasRole(RoleName::SuperAdmin->value)) {
            return Tenant::query()->findOrFail($tenantId);
        }

        return $this->tenantContext->bypass(
            'tenant lookup (super admin)',
            fn () => Tenant::query()->findOrFail($tenantId),
        );
    }

    /**
     * @param  array{name: string, slug: string, status?: string}  $data
     */
    public function create(array $data): Tenant
    {
        return $this->tenantContext->bypass('tenant provisioning', function () use ($data) {
            if (Tenant::query()->where('slug', $data['slug'])->exists()) {
                throw ValidationException::withMessages([
                    'slug' => [__('validation.unique', ['attribute' => __('validation.attributes.slug')])],
                ]);
            }

            return Tenant::query()->create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Tenant $tenant, array $data): Tenant
    {
        $update = function () use ($tenant, $data) {
            $tenant->fill($data)->save();

            return $tenant;
        };

        return $actor->hasRole(RoleName::SuperAdmin->value)
            ? $this->tenantContext->bypass('tenant update (super admin)', $update)
            : $update();
    }

    public function delete(Tenant $tenant): void
    {
        $this->tenantContext->bypass('tenant suspension (soft delete)', function () use ($tenant) {
            $tenant->delete();
        });
    }
}
