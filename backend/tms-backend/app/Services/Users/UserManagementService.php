<?php

namespace App\Services\Users;

use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\UserAccountCreatedNotification;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

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

        $query = fn () => User::query()
            ->when($tenantIdFilter !== null, fn ($query) => $query->where('tenant_id', $tenantIdFilter))
            ->orderBy('name')
            ->paginate($perPage);

        return $this->tenantContext->bypass(
            'platform-wide user listing',
            // Spatie's "teams" feature scopes model_has_roles by
            // PermissionsTeamId independently of the RLS bypass above —
            // with('roles') here would come back empty for every row
            // without binding it explicitly (see TenantContext::withTeam()'s
            // own docblock). A tenant filter lets every row share one team;
            // without one (every tenant at once), no single team id can
            // correctly scope a mixed-tenant listing in one eager-load, so
            // each row's own tenant is bound individually instead — more
            // queries, but correct regardless of how many different
            // tenants happen to be on the page.
            function () use ($query, $tenantIdFilter) {
                $users = $query();

                if ($tenantIdFilter !== null) {
                    $this->tenantContext->withTeam($tenantIdFilter, fn () => $users->load('roles'));
                } else {
                    foreach ($users as $user) {
                        $this->tenantContext->withTeam($user->tenant_id, fn () => $user->load('roles'));
                    }
                }

                return $users;
            },
        );
    }

    public function find(User $actor, string $userId): User
    {
        if (! $actor->hasRole(RoleName::SuperAdmin->value)) {
            return User::query()->with(['roles', 'qualifiedTicketTypes'])->findOrFail($userId);
        }

        return $this->tenantContext->bypass(
            'user lookup (super admin)',
            function () use ($userId) {
                $user = User::query()->with('qualifiedTicketTypes')->findOrFail($userId);

                // See paginate()'s own comment: roles() is team-scoped by
                // Spatie independently of the RLS bypass above — bind the
                // target's own tenant, not the acting Super Admin's.
                $this->tenantContext->withTeam($user->tenant_id, fn () => $user->load('roles'));

                return $user;
            },
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

            // The login form resolves a user by (tenant slug, email) — a
            // tenant-scoped user (anyone but a Super Admin) literally cannot
            // sign in without knowing this slug, and has no other way to
            // learn it than being told here.
            $tenantSlug = $targetTenantId !== null
                ? Tenant::query()->whereKey($targetTenantId)->value('slug')
                : null;

            // The admin chose this password themselves (it's a required,
            // plaintext input on the create-user form) — the new user has no
            // other way to learn it, so it must go out over the one channel
            // that doesn't persist it server-side (see the notification's
            // own docblock for why mail-only).
            $user->notify(new UserAccountCreatedNotification($data['email'], $data['password'], $tenantSlug));

            // Regardless of whether $roles is empty: UserController::store()
            // serializes this returned model directly (no further
            // ->load('roles') of its own) — doing that afterward, once the
            // bypass/team context below has already ended, would silently
            // come back empty for a Super Admin (see paginate()'s own
            // comment on why). The whole closure runs team-bound to
            // $targetTenantId from the start via bypassForTarget() below,
            // not just this tail — harmless, since nothing before this
            // point touches Spatie roles/permissions at all.
            if ($roles !== []) {
                $user->syncRoles($roles);
            }

            $user->load('roles');

            return $user;
        };

        return $isSuperAdmin
            ? $this->tenantContext->bypassForTarget($targetTenantId, 'user provisioning (super admin)', $create)
            : $create();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, User $target, array $data): User
    {
        $isSuperAdmin = $actor->hasRole(RoleName::SuperAdmin->value);

        // UpdateUserRequest only ever validates name/email, never tenant_id
        // — $target->tenant_id can't change over the course of $update
        // below, so it's safe to capture once, upfront, for
        // bypassForTarget() rather than re-reading it after fill()/save().
        $targetTenantId = $target->tenant_id;

        $update = function () use ($target, $data) {
            if (isset($data['email']) && $data['email'] !== $target->email) {
                $this->assertEmailAvailable($data['email'], $target->tenant_id, $target->id);
            }

            $target->fill($data)->save();

            // Same reasoning as create(): UserController::update()
            // serializes this returned model directly, and roles() is
            // team-scoped by Spatie independently of the bypass above —
            // the whole closure runs team-bound via bypassForTarget()
            // below, harmless since nothing before this point touches
            // Spatie roles/permissions.
            $target->load('roles');

            return $target;
        };

        return $isSuperAdmin
            ? $this->tenantContext->bypassForTarget($targetTenantId, 'user update (super admin)', $update)
            : $update();
    }

    public function delete(User $actor, User $target): void
    {
        $delete = function () use ($target) {
            $this->assertNoActiveTickets($target);
            $target->delete();
        };

        $actor->hasRole(RoleName::SuperAdmin->value)
            ? $this->tenantContext->bypass('user deactivation (super admin)', $delete)
            : $delete();
    }

    /**
     * Deactivating (soft-deleting) a user does not touch their tickets at
     * all — User::delete() never reaches the `tickets.client_id`/`agent_id`
     * foreign keys' restrictOnDelete()/nullOnDelete() behavior, since those
     * only fire on a genuine hard delete. Left unchecked, a deactivated
     * agent's still-open tickets would sit assigned to someone who can no
     * longer act on them (or log in at all), invisible to everyone until
     * someone happens to notice; a deactivated client's open ticket would
     * lose the one person who can supply the info needed to close it.
     * Blocking outright (rather than silently unassigning/reopening
     * something for the caller) forces the reassignment/closure to happen
     * as its own deliberate, visible step first — see TicketService::assign()
     * (reassign to someone else) or unassign() (send back to the pool).
     */
    private function assertNoActiveTickets(User $target): void
    {
        $activeStatuses = fn ($query) => $query->whereNotIn('status', [
            TicketStatus::Resolved->value,
            TicketStatus::Closed->value,
        ]);

        if (Ticket::query()->where('client_id', $target->id)->tap($activeStatuses)->exists()) {
            throw ValidationException::withMessages([
                'user' => [__('messages.users.has_active_tickets_as_client')],
            ]);
        }

        if (Ticket::query()->where('agent_id', $target->id)->tap($activeStatuses)->exists()) {
            throw ValidationException::withMessages([
                'user' => [__('messages.users.has_active_tickets_as_agent')],
            ]);
        }
    }

    /**
     * @param  list<string>  $roles
     */
    public function syncRoles(User $actor, User $target, array $roles): User
    {
        $this->assertRoleGrantAllowed($roles, $target->tenant_id);

        // .load('roles') joins syncRoles() inside the same withTeam() call,
        // not after: UserController::(store|update)() already showed this
        // exact class of bug (roles() is team-scoped by Spatie independently
        // of the RLS bypass), and this endpoint returns the model directly
        // the same way.
        $sync = fn () => $this->tenantContext->withTeam($target->tenant_id, function () use ($target, $roles) {
            $target->syncRoles($roles);
            $target->load('roles');
        });

        $actor->hasRole(RoleName::SuperAdmin->value)
            ? $this->tenantContext->bypass('user role sync (super admin)', $sync)
            : $sync();

        return $target;
    }

    /**
     * @param  list<string>  $ticketTypeIds
     */
    public function syncSkills(User $actor, User $target, array $ticketTypeIds): User
    {
        $sync = function () use ($target, $ticketTypeIds) {
            // No tenant-membership check needed here: ticket_types is a
            // global catalog (see TicketType's own docblock), so every id
            // AssignAgentSkillsRequest's `exists:ticket_types,id` already
            // validated is a legitimate skill for any agent in any tenant.

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
}
