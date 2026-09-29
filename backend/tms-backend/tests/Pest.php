<?php

use App\Enums\RoleName;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Session variables persist on the underlying PDO connection across
        // tests in the same run (RefreshDatabase only wraps each test in a
        // transaction, it doesn't reconnect), so every test must start clean.
        DB::statement('RESET app.tenant_id');
        DB::statement('RESET app.bypass_rls');
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * Provisioning a brand new tenant (and its first user) can never satisfy a
 * tenant-match RLS check — the row doesn't exist yet — so, exactly as in
 * production, fixture setup goes through the audited bypass.
 *
 * @param  array<string, mixed>  $userAttributes
 * @return array{0: Tenant, 1: User}
 */
function createTenantWithUser(array $userAttributes = []): array
{
    return app(TenantContext::class)->bypass('test fixture setup', function () use ($userAttributes) {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create($userAttributes);

        return [$tenant, $user];
    });
}

/**
 * A platform-level Super Admin (tenant_id IS NULL), role-assigned and ready
 * to log in. Requires RoleAndPermissionSeeder to have run first (the Super
 * Admin role must exist) — same precondition as assignRole() anywhere else.
 */
function createSuperAdmin(): User
{
    return app(TenantContext::class)->bypass('test fixture setup', function () {
        $superAdmin = User::factory()->superAdmin()->create();
        // Spatie's team resolver needs the reserved platform team bound
        // before assignRole() for a tenant-less user — see
        // TenantContext::PLATFORM_TEAM_ID.
        app(TenantContext::class)->bindPlatformTeam();
        $superAdmin->assignRole(RoleName::SuperAdmin->value);

        return $superAdmin;
    });
}

/**
 * A tenant with a ticket type, a client, a support agent, and one Pending,
 * unassigned ticket owned by the client. Role seeding/assignment is left to
 * the caller (not every ticket test needs RBAC) — see RoleAndPermissionSeeder.
 *
 * @param  array<string, mixed>  $ticketAttributes
 * @return array{0: Tenant, 1: TicketType, 2: User client, 3: User agent, 4: Ticket}
 */
function createTicketFixture(array $ticketAttributes = []): array
{
    return app(TenantContext::class)->bypass('test fixture setup', function () use ($ticketAttributes) {
        $tenant = Tenant::factory()->create();
        $ticketType = TicketType::factory()->create();
        $client = User::factory()->for($tenant)->create();
        $agent = User::factory()->for($tenant)->create();

        $ticket = Ticket::factory()->for($tenant)->create(array_merge([
            'ticket_type_id' => $ticketType->id,
            'client_id' => $client->id,
        ], $ticketAttributes));

        return [$tenant, $ticketType, $client, $agent, $ticket];
    });
}

/**
 * Logs in via the real HTTP endpoint (exercises the actual auth flow, not a
 * shortcut) and returns the bearer token.
 */
function loginAs(string $tenantSlug, string $email, string $password = 'password'): string
{
    return test()->postJson('/api/v1/auth/login', [
        'tenant' => $tenantSlug,
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('data.token');
}
