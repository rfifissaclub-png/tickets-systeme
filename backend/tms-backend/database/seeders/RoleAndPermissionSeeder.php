<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the five fixed platform roles as system-wide (tenant_id IS NULL)
 * roles, visible to every tenant, plus the permission catalog.
 *
 * Super Admin is granted every permission here for consistency with
 * hasPermissionTo() checks, even though Gate::before in AppServiceProvider
 * already grants it everything unconditionally.
 */
class RoleAndPermissionSeeder extends Seeder
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'users.viewAny',
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'tenants.view',
        'tenants.update',
        'tenants.manage',
        'tickets.viewAny',
        'tickets.view',
        'tickets.create',
        'tickets.update',
        'tickets.delete',
        'tickets.assign',
        'tickets.pull',
        'tickets.viewInternal',
        'ticket_types.manage',
    ];

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        RoleName::TenantAdmin->value => [
            'users.viewAny', 'users.view', 'users.create', 'users.update', 'users.delete',
            'tenants.view', 'tenants.update',
            'tickets.viewAny', 'tickets.view', 'tickets.create', 'tickets.update', 'tickets.delete',
            'tickets.assign', 'tickets.viewInternal', 'ticket_types.manage',
        ],
        RoleName::Manager->value => [
            'users.viewAny', 'users.view', 'users.update',
            'tickets.viewAny', 'tickets.view', 'tickets.create', 'tickets.update',
            'tickets.assign', 'tickets.viewInternal',
        ],
        // Deliberately no tickets.update: an agent's ability to edit a
        // ticket comes only from TicketPolicy's ownership check (they're
        // the assigned agent), not a blanket permission — one agent should
        // not be able to freely edit another agent's ticket.
        RoleName::SupportAgent->value => [
            'users.view',
            'tickets.viewAny', 'tickets.view', 'tickets.create', 'tickets.pull', 'tickets.viewInternal',
        ],
        // Deliberately no tickets.view/viewAny: a Client's access to their
        // own tickets comes only from TicketPolicy's ownership check, never
        // from a permission — granting tickets.view here would let any
        // Client read any other client's tickets in the tenant.
        RoleName::Client->value => ['tickets.create'],
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        // These are system-wide roles, not scoped to whatever tenant happens
        // to be bound in the current context (there usually isn't one when
        // this runs, but be explicit rather than rely on that).
        $registrar->setPermissionsTeamId(null);

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate(RoleName::SuperAdmin->value, 'web')->syncPermissions(self::PERMISSIONS);

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }

        $registrar->forgetCachedPermissions();
    }
}
