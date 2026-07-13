<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('lets a Tenant Admin view their own tenant\'s audit log but not another tenant\'s', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    [$otherTenant, $otherUser] = createTenantWithUser();
    app(TenantContext::class)->bypass(
        'test fixture setup',
        fn () => AuditLog::query()->create([
            'tenant_id' => $otherTenant->id,
            'actor_id' => $otherUser->id,
            'action' => 'test.other_tenant_action',
            'created_at' => now(),
        ]),
    );

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    // The role assignment above already produced at least one row (see
    // RoleChangeAuditTest) — assert on content, not just a non-empty list.
    $response = $this->withToken($token)->getJson('/api/v1/audit-logs')->assertOk();

    $actions = collect($response->json('data'))->pluck('action');

    expect($actions)->toContain('role.attached')
        ->and($actions)->not->toContain('test.other_tenant_action');
});

it('rejects a Manager viewing the audit log', function () {
    [$tenant, $manager] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $manager->assignRole(RoleName::Manager->value);

    $token = loginAs($tenant->slug, $manager->email);

    $this->withToken($token)->getJson('/api/v1/audit-logs')->assertForbidden();
});

it('lets a Super Admin view the platform-wide audit log filtered by tenant', function () {
    [$tenantA, $userA] = createTenantWithUser();
    [$tenantB, $userB] = createTenantWithUser();

    app(TenantContext::class)->bypass('test fixture setup', function () use ($tenantA, $userA, $tenantB, $userB) {
        AuditLog::query()->create(['tenant_id' => $tenantA->id, 'actor_id' => $userA->id, 'action' => 'test.tenant_a', 'created_at' => now()]);
        AuditLog::query()->create(['tenant_id' => $tenantB->id, 'actor_id' => $userB->id, 'action' => 'test.tenant_b', 'created_at' => now()]);
    });

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $response = $this->withToken($token)
        ->getJson("/api/v1/audit-logs?tenant_id={$tenantA->id}")
        ->assertOk();

    $actions = collect($response->json('data'))->pluck('action');

    expect($actions)->toContain('test.tenant_a')
        ->and($actions)->not->toContain('test.tenant_b');
});
