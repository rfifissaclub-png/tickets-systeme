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

        $this->seedMainCorporation();
        $this->seedTicketTypeCatalog();

        if (app()->isProduction()) {
            return;
        }

        $this->seedLocalDevAccounts();
    }

    /**
     * The cahier des charges' "Corporation Principale" — see
     * Tenant::MAIN_CORPORATION_SLUG. Seeded in every environment, including
     * production, the same way roles/permissions are: this is a structural
     * part of the tenancy model (every client belongs to a specific entity
     * *or* this one), not local-dev-only demo data. firstOrCreate rather
     * than create: re-running seeders against an already-seeded database
     * (a common deploy-step pattern) must not throw on the slug's unique
     * constraint.
     */
    private function seedMainCorporation(): void
    {
        app(TenantContext::class)->bypass(
            'seed: main corporation tenant',
            fn () => Tenant::query()->firstOrCreate(
                ['slug' => Tenant::MAIN_CORPORATION_SLUG],
                ['name' => 'Main Corporation'],
            ),
        );
    }

    /**
     * Matches the cahier des charges' own worked example (Support
     * Informatique, Facturation, Ressources Humaines). A single
     * platform-wide catalog (see TicketType's own docblock), seeded in
     * every environment including production, the same way
     * roles/permissions and Main Corporation are. firstOrCreate by name:
     * re-running seeders against an already-seeded database must not throw
     * on the now-global unique constraint.
     */
    private function seedTicketTypeCatalog(): void
    {
        foreach (['IT Support', 'Billing', 'Human Resources'] as $name) {
            TicketType::query()->firstOrCreate(['name' => $name]);
        }
    }

    /**
     * Demo accounts for local development only: a platform Super Admin, a
     * tenant with a Tenant Admin/Support Agent/Client, and a second tenant
     * (Tenant Admin + Client only) for exercising cross-tenant isolation
     * without hand-creating throwaway users every time — all password
     * "password".
     */
    private function seedLocalDevAccounts(): void
    {
        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);

        // Provisioning a Super Admin (tenant_id IS NULL) and a brand new
        // tenant both require the bypass — see TenantContext::bypass().
        // Spatie's team resolver also needs the reserved platform team
        // bound before assignRole() — see TenantContext::PLATFORM_TEAM_ID.
        $tenantContext->bypass('local dev seed: platform super admin', function () use ($tenantContext) {
            // Ensure idempotent creation: if the super admin already exists,
            // reuse it instead of creating a duplicate (unique email constraint).
            $superAdmin = User::query()->where('email', 'super-admin@tms.local')->first();

            if (! $superAdmin) {
                $superAdmin = User::factory()->superAdmin()->create([
                    'name' => 'Platform Super Admin',
                    'email' => 'super-admin@tms.local',
                ]);
            }

            $tenantContext->bindPlatformTeam();

            if (! $superAdmin->hasRole(RoleName::SuperAdmin->value)) {
                $superAdmin->assignRole(RoleName::SuperAdmin->value);
            }
        });

        $tenant = $tenantContext->bypass(
            'local dev seed: demo tenant',
            fn () => Tenant::query()->firstOrCreate(
                ['slug' => 'acme'],
                ['name' => 'Acme Support'],
            ),
        );

        // Bind context to the new tenant so the Tenant Admin below is
        // created (and its role assignment scoped) the same way a real
        // tenant-scoped request would do it — no bypass needed past this
        // point, tenant_id already matches the bound context.
        $tenantContext->set($tenant->id);

        $tenantAdmin = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'admin@acme.tms.local')
            ->first();

        if (! $tenantAdmin) {
            $tenantAdmin = User::factory()->for($tenant)->create([
                'name' => 'Acme Tenant Admin',
                'email' => 'admin@acme.tms.local',
            ]);
        }

        if (! $tenantAdmin->hasRole(RoleName::TenantAdmin->value)) {
            $tenantAdmin->assignRole(RoleName::TenantAdmin->value);
        }

        // Ticket types are the global catalog seedTicketTypeCatalog() just
        // created, not this tenant's own — every tenant shares the same set.
        $itSupport = TicketType::query()->where('name', 'IT Support')->firstOrFail();

        // A Support Agent qualified only for IT Support — demonstrates the
        // pool's skill-based filtering (see TicketPoolService::poolQuery())
        // out of the box instead of it only being visible once someone
        // configures skills by hand.
        $agent = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'agent@acme.tms.local')
            ->first();

        if (! $agent) {
            $agent = User::factory()->for($tenant)->create([
                'name' => 'Acme Support Agent',
                'email' => 'agent@acme.tms.local',
            ]);
        }

        if (! $agent->hasRole(RoleName::SupportAgent->value)) {
            $agent->assignRole(RoleName::SupportAgent->value);
        }

        // Attach qualification only if not already present to avoid pivot unique violations.
        $hasQualification = $agent->qualifiedTicketTypes()->where('ticket_types.id', $itSupport->id)->exists();
        if (! $hasQualification) {
            $agent->qualifiedTicketTypes()->attach($itSupport->id, ['tenant_id' => $tenant->id]);
        }

        // A Client, still bound to Acme's team from tenantContext->set()
        // above — every role-gated page/endpoint needs a real Client
        // account to demo/test the "denied" side, not just the three
        // staff roles.
        $client = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'client@acme.tms.local')
            ->first();

        if (! $client) {
            $client = User::factory()->for($tenant)->create([
                'name' => 'Acme Client',
                'email' => 'client@acme.tms.local',
            ]);
        }

        if (! $client->hasRole(RoleName::Client->value)) {
            $client->assignRole(RoleName::Client->value);
        }

        // A second tenant, entirely separate from Acme — exists solely so
        // cross-tenant isolation (a Tenant Admin/Client never seeing the
        // other tenant's data) can be demoed/tested without hand-creating
        // one, the same way this was done manually, repeatedly, before.
        $globex = $tenantContext->bypass(
            'local dev seed: second demo tenant',
            fn () => Tenant::query()->firstOrCreate(
                ['slug' => 'globex'],
                ['name' => 'Globex Corp'],
            ),
        );

        $tenantContext->set($globex->id);

        $globexAdmin = User::query()
            ->where('tenant_id', $globex->id)
            ->where('email', 'admin@globex.tms.local')
            ->first();

        if (! $globexAdmin) {
            $globexAdmin = User::factory()->for($globex)->create([
                'name' => 'Globex Tenant Admin',
                'email' => 'admin@globex.tms.local',
            ]);
        }

        if (! $globexAdmin->hasRole(RoleName::TenantAdmin->value)) {
            $globexAdmin->assignRole(RoleName::TenantAdmin->value);
        }

        $globexClient = User::query()
            ->where('tenant_id', $globex->id)
            ->where('email', 'client@globex.tms.local')
            ->first();

        if (! $globexClient) {
            $globexClient = User::factory()->for($globex)->create([
                'name' => 'Globex Client',
                'email' => 'client@globex.tms.local',
            ]);
        }

        if (! $globexClient->hasRole(RoleName::Client->value)) {
            $globexClient->assignRole(RoleName::Client->value);
        }
    }
}
