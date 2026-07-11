<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Tenant;
use App\Models\TicketType;
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

        // Matches the cahier des charges' own worked example (Support
        // Informatique, Facturation, Ressources Humaines) so a fresh demo
        // tenant is immediately usable end-to-end, not an empty shell an
        // admin has to configure from scratch before filing a first ticket.
        $itSupport = TicketType::factory()->for($tenant)->create(['name' => 'IT Support']);
        TicketType::factory()->for($tenant)->create(['name' => 'Billing']);
        TicketType::factory()->for($tenant)->create(['name' => 'Human Resources']);

        // A Support Agent qualified only for IT Support — demonstrates the
        // pool's skill-based filtering (see TicketPoolService::poolQuery())
        // out of the box instead of it only being visible once someone
        // configures skills by hand.
        $agent = User::factory()->for($tenant)->create([
            'name' => 'Acme Support Agent',
            'email' => 'agent@acme.tms.local',
        ]);
        $agent->assignRole(RoleName::SupportAgent->value);
        $agent->qualifiedTicketTypes()->attach($itSupport->id, ['tenant_id' => $tenant->id]);
    }
}
