<?php

namespace App\Services\Users;

use App\Enums\RoleName;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * A Tenant Admin's own bound session already RLS-scopes every query here to
 * their own tenant, so most of the "bypass" reasoning below only ever
 * actually engages for a Super Admin actor operating across tenants (or
 * provisioning a platform-level account with no tenant at all) — the same
 * pattern as TenantManagementService. Authorization is the caller's
 * responsibility (UserController/UserRoleController/AgentSkillController
 * check the relevant policy before calling in here).
 */
class UserManagementService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(User $actor, ?string $tenantIdFilter, int $perPage): LengthAwarePaginator
    {
        if (! $actor->hasRole(RoleName::SuperAdmin->value)) {
            return User::query()->with('roles')->orderBy('name')->paginate($perPage);
        }

        return $this->tenantContext->bypass(
            'platform-wide user listing',
            fn () => User::query()
                ->when($tenantIdFilter !== null, fn ($query) => $query->where('tenant_id', $tenantIdFilter))
                ->with('roles')
                ->orderBy('name')
                ->paginate($perPage),
        );
    }

    public function find(User $actor, string $userId): User
    {
        if (! $actor->hasRole(RoleName::SuperAdmin->value)) {
            return User::query()->with(['roles', 'qualifiedTicketTypes'])->findOrFail($userId);
        }

        return $this->tenantContext->bypass(
            'user lookup (super admin)',
            fn () => User::query()->with(['roles', 'qualifiedTicketTypes'])->findOrFail($userId),
        );
    }

    /**
     * @param  array{tenant_id?: string|null, name: string, email: string, password: string, roles?: list<string>}  $data
     */
    public function create(User $actor, array $data): User
    {
        $isSuperAdmin = $actor->hasRole(RoleName::SuperAdmin->value);

        // A Tenant Admin can only ever provision a user inside their own
        // tenant — the `tenant_id` input (if any) is only honored for a
        // Super Admin actor; anyone else's is silently overridden rather
        // than trusted, regardless of what the request body claims.
        $targetTenantId = $isSuperAdmin ? ($data['tenant_id'] ?? null) : $this->tenantContext->requireTenant();
        $roles = $data['roles'] ?? [];

        $this->assertRoleGrantAllowed($roles, $targetTenantId);

        $create = function () use ($data, $targetTenantId, $roles) {
            $this->assertEmailAvailable($data['email'], $targetTenantId);

            $user = User::query()->create([
                'tenant_id' => $targetTenantId,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            // Admin-provisioned, not self-registered: there's no signup flow
            // to have already confirmed the address, so send the normal
            // verification email rather than marking it pre-verified.
            $user->sendEmailVerificationNotification();

            if ($roles !== []) {
                $this->withTeamContext($targetTenantId, fn () => $user->syncRoles($roles));
            }

            return $user;
        };

        return $isSuperAdmin
            ? $this->tenantContext->bypass('user provisioning (super admin)', $create)
            : $create();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, User $target, array $data): User
    {
        $isSuperAdmin = $actor->hasRole(RoleName::SuperAdmin->value);

        $update = function () use ($target, $data) {
            if (isset($data['email']) && $data['email'] !== $target->email) {
                $this->assertEmailAvailable($data['email'], $target->tenant_id, $target->id);
            }

            $target->fill($data)->save();

            return $target;
        };

        return $isSuperAdmin ? $this->tenantContext->bypass('user update (super admin)', $update) : $update();
    }

    public function delete(User $actor, User $target): void
    {
        $delete = function () use ($target) {
            $target->delete();
        };

        $actor->hasRole(RoleName::SuperAdmin->value)
            ? $this->tenantContext->bypass('user deactivation (super admin)', $delete)
            : $delete();
    }

    /**
     * @param  list<string>  $roles
     */
    public function syncRoles(User $actor, User $target, array $roles): User
    {
        $this->assertRoleGrantAllowed($roles, $target->tenant_id);

        $sync = fn () => $this->withTeamContext($target->tenant_id, fn () => $target->syncRoles($roles));

        $actor->hasRole(RoleName::SuperAdmin->value)
            ? $this->tenantContext->bypass('user role sync (super admin)', $sync)
            : $sync();

        return $target->load('roles');
    }

    /**
     * @param  list<string>  $ticketTypeIds
     */
    public function syncSkills(User $actor, User $target, array $ticketTypeIds): User
    {
        $sync = function () use ($target, $ticketTypeIds) {
            // Belt-and-suspenders alongside AssignAgentSkillsRequest's own
            // `exists:ticket_types,id` rule: that rule is RLS-scoped to
            // whichever tenant validated the request, but a Super Admin
            // actor's bypassed sync here must independently confirm every
            // id actually belongs to the target's own tenant, not any
            // tenant a Super Admin happens to also be able to see.
            $validIds = TicketType::query()
                ->where('tenant_id', $target->tenant_id)
                ->whereIn('id', $ticketTypeIds)
                ->pluck('id');

            if ($validIds->count() !== count($ticketTypeIds)) {
                throw ValidationException::withMessages([
                    'ticket_type_ids' => [__('messages.users.skills_must_match_tenant')],
                ]);
            }

            // sync()'s array-with-pivot-attributes form is required here,
            // not the plain id-list form: agent_skills carries its own
            // tenant_id column (for RLS), which a bare sync($ids) would
            // never populate, failing the NOT NULL constraint on insert.
            $target->qualifiedTicketTypes()->sync(
                collect($ticketTypeIds)->mapWithKeys(fn (string $id) => [$id => ['tenant_id' => $target->tenant_id]]),
            );

            return $target;
        };

        $actor->hasRole(RoleName::SuperAdmin->value)
            ? $this->tenantContext->bypass('agent skill sync (super admin)', $sync)
            : $sync();

        return $target->load('qualifiedTicketTypes');
    }

    /**
     * @param  list<string>  $roles
     */
    private function assertRoleGrantAllowed(array $roles, ?string $targetTenantId): void
    {
        if (in_array(RoleName::SuperAdmin->value, $roles, true) && $targetTenantId !== null) {
            throw ValidationException::withMessages([
                'roles' => [__('messages.users.super_admin_role_requires_no_tenant')],
            ]);
        }
    }

    private function assertEmailAvailable(string $email, ?string $tenantId, ?string $ignoreUserId = null): void
    {
        $exists = User::query()
            ->where('email', $email)
            ->when(
                $tenantId === null,
                fn ($query) => $query->whereNull('tenant_id'),
                fn ($query) => $query->where('tenant_id', $tenantId),
            )
            ->when($ignoreUserId !== null, fn ($query) => $query->whereKeyNot($ignoreUserId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => [__('validation.unique', ['attribute' => __('validation.attributes.email')])],
            ]);
        }
    }

    /**
     * Spatie's "teams" feature scopes every role/permission pivot row by
     * PermissionsTeamId, independently of PostgreSQL RLS (those pivot
     * tables aren't RLS-protected at all — see create_permission_tables).
     * A Tenant Admin's own bound request already has this set correctly to
     * their tenant; a Super Admin's never is (they have no tenant of their
     * own — see ResolveTenantContext), so role/skill writes on their behalf
     * must set it explicitly for the target's tenant, or the pivot row
     * would be created scoped to no tenant at all. `$tenantId` is passed
     * through TenantContext::teamIdFor(), which substitutes the reserved
     * platform team for a null (tenant-less target) — `team_foreign_key` is
     * part of the composite primary key on the assignment pivot tables, so
     * Postgres rejects a literal null there outright (see
     * TenantContext::PLATFORM_TEAM_ID for the full explanation).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withTeamContext(?string $tenantId, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId(TenantContext::teamIdFor($tenantId));

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
