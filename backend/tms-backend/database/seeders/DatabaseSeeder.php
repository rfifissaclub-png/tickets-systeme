<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Roles/permissions are needed in every environment, including production.
        $this->call(RoleAndPermissionSeeder::class);

        if (app()->isProduction()) {
            return;
        }

        $this->seedLocalDevAccounts();
    }

    /**
     * Demo accounts for local development only: a platform Super Admin and
     * one tenant with a Tenant Admin, both with password "password".
     */
    private function seedLocalDevAccounts(): void
    {
        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);

        // Provisioning a Super Admin (tenant_id IS NULL) and a brand new
        // tenant both require the bypass — see TenantContext::bypass().
        $tenantContext->bypass('local dev seed: platform super admin', function () {
            $superAdmin = User::factory()->superAdmin()->create([
                'name' => 'Platform Super Admin',
                'email' => 'super-admin@tms.local',
            ]);
            $superAdmin->assignRole(RoleName::SuperAdmin->value);
        });

        $tenant = $tenantContext->bypass(
            'local dev seed: demo tenant',
            fn () => Tenant::factory()->create(['name' => 'Acme Support', 'slug' => 'acme']),
        );

        // Bind context to the new tenant so the Tenant Admin below is
        // created (and its role assignment scoped) the same way a real
        // tenant-scoped request would do it — no bypass needed past this
        // point, tenant_id already matches the bound context.
        $tenantContext->set($tenant->id);

        $tenantAdmin = User::factory()->for($tenant)->create([
            'name' => 'Acme Tenant Admin',
            'email' => 'admin@acme.tms.local',
        ]);
        $tenantAdmin->assignRole(RoleName::TenantAdmin->value);
    }
}
