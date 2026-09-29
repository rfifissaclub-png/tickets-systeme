<?php

namespace App\Services\Tenancy;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

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

    /**
     * Find a tenant by UUID or slug. Accepts either the UUID primary key
     * or the human-friendly slug used in URLs; preserves the same RLS and
     * Super Admin bypass semantics as before.
     *
     * @param string $tenantIdOrSlug
     */
    public function find(User $actor, string $tenantIdOrSlug): Tenant
    {
        // Avoid passing a non-UUID string into a UUID comparison which
        // Postgres will reject with "invalid input syntax for type uuid".
        $isUuid = (bool) preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $tenantIdOrSlug);

        $query = function () use ($tenantIdOrSlug, $isUuid) {
            $q = Tenant::query();

            if ($isUuid) {
                return $q->where('id', $tenantIdOrSlug)->firstOrFail();
            }

            return $q->where('slug', $tenantIdOrSlug)->firstOrFail();
        };

        if (! $actor->hasRole(RoleName::SuperAdmin->value)) {
            return $query();
        }

        return $this->tenantContext->bypass('tenant lookup (super admin)', $query);
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
            $this->reassignClientsToMainCorporation($tenant);

            $tenant->delete();
        });
    }

    /**
     * Suspending a tenant permanently locks out its staff (Tenant
     * Admin/Manager/Support Agent) — there is no other entity for those
     * roles to mean anything under, so they're deliberately left as-is
     * (login already fails for them once the tenant is soft-deleted, since
     * the login lookup only ever queries non-trashed tenants).
     *
     * Clients are different: Main Corporation exists precisely to hold
     * clients not tied to a specific business entity, so a deleted
     * tenant's clients are moved there instead of being locked out, and
     * can keep signing in and filing new tickets. Their *historical*
     * tickets are deliberately left tied to the now-trashed tenant rather
     * than migrated along with them — a ticket's agent_id/ticket_type_id
     * both belong to the old tenant, and moving the ticket without moving
     * those would break tenant-scoped assumptions for the very data being
     * "preserved". Must run inside the same RLS-bypassed block as the
     * tenant's own soft-delete: this reassigns rows across two tenants at
     * once, which RLS would otherwise block outright.
     */
    private function reassignClientsToMainCorporation(Tenant $tenant): void
    {
        $mainCorporation = Tenant::query()->where('slug', Tenant::MAIN_CORPORATION_SLUG)->first();

        if ($mainCorporation === null || $tenant->is($mainCorporation)) {
            return;
        }

        $clientRoleId = Role::query()->where('name', RoleName::Client->value)->whereNull('tenant_id')->value('id');

        if ($clientRoleId === null) {
            return;
        }

        $clientIds = DB::table('model_has_roles')
            ->where('tenant_id', $tenant->id)
            ->where('role_id', $clientRoleId)
            ->where('model_type', User::class)
            ->pluck('model_id');

        if ($clientIds->isEmpty()) {
            return;
        }

        // The (tenant_id, email) unique constraint on `users` would turn a
        // clash into a raw DB error mid-move; surface it as a clean,
        // actionable validation error instead, before anything is written.
        $conflictingEmails = User::query()
            ->where('tenant_id', $mainCorporation->id)
            ->whereIn('email', User::query()->whereIn('id', $clientIds)->pluck('email'))
            ->pluck('email');

        if ($conflictingEmails->isNotEmpty()) {
            throw ValidationException::withMessages([
                'tenant' => [__('messages.tenants.client_email_conflict', ['emails' => $conflictingEmails->implode(', ')])],
            ]);
        }

        DB::table('model_has_roles')
            ->where('tenant_id', $tenant->id)
            ->where('role_id', $clientRoleId)
            ->where('model_type', User::class)
            ->whereIn('model_id', $clientIds)
            ->update(['tenant_id' => $mainCorporation->id]);

        User::query()->whereIn('id', $clientIds)->update(['tenant_id' => $mainCorporation->id]);

        // The role pivot row was updated with a raw query, bypassing the
        // model events Spatie normally uses to invalidate its own cache.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
