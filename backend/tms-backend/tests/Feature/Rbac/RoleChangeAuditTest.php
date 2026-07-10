<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('logs a role assignment to the audit trail', function () {
    [$tenant, $user] = createTenantWithUser();

    app(TenantContext::class)->set($tenant->id);

    $user->assignRole(RoleName::Manager->value);

    expect(
        AuditLog::query()
            ->where('action', 'role.attached')
            ->where('auditable_id', $user->id)
            ->exists()
    )->toBeTrue();
});

it('logs a role removal to the audit trail', function () {
    [$tenant, $user] = createTenantWithUser();

    app(TenantContext::class)->set($tenant->id);

    $user->assignRole(RoleName::Manager->value);
    $user->removeRole(RoleName::Manager->value);

    expect(
        AuditLog::query()
            ->where('action', 'role.detached')
            ->where('auditable_id', $user->id)
            ->exists()
    )->toBeTrue();
});
