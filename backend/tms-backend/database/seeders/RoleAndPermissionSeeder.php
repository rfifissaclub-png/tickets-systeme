<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the five fixed platform roles as system-wide (tenant_id IS NULL)
 * roles, visible to every tenant, plus the permission catalog buildable so
 * far (user/tenant management — ticket-domain permissions land in Phase 3
 * alongside the ticket model itself).
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
    ];

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        RoleName::TenantAdmin->value => [
            'users.viewAny', 'users.view', 'users.create', 'users.update', 'users.delete',
            'tenants.view', 'tenants.update',
        ],
        RoleName::Manager->value => ['users.viewAny', 'users.view', 'users.update'],
        RoleName::SupportAgent->value => ['users.view'],
        RoleName::Client->value => [],
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
