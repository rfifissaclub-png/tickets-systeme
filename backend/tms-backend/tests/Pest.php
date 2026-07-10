<?php

use App\Models\Tenant;
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
    ->in('Feature', 'Unit');

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
