<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Proves the PostgreSQL RLS policies from the enable_row_level_security
 * migration actually isolate tenants at the database engine level — not
 * just via application-side query scopes, which a bug elsewhere could skip.
 */
it('fails closed when no tenant context is bound', function () {
    createTenantWithUser();

    expect(User::count())->toBe(0)
        ->and(Tenant::count())->toBe(0);
});

it('only exposes rows belonging to the bound tenant', function () {
    [$tenantA, $userA] = createTenantWithUser();
    [$tenantB, $userB] = createTenantWithUser();

    app(TenantContext::class)->set($tenantA->id);

    expect(User::pluck('id')->all())->toBe([$userA->id])
        ->and(User::find($userB->id))->toBeNull()
        ->and(Tenant::count())->toBe(1);

    app(TenantContext::class)->set($tenantB->id);

    expect(User::pluck('id')->all())->toBe([$userB->id])
        ->and(User::find($userA->id))->toBeNull();
});

it('allows cross-tenant reads only through an audited bypass, and records why', function () {
    [$tenantA, $userA] = createTenantWithUser();
    [, $userB] = createTenantWithUser();

    app(TenantContext::class)->set($tenantA->id);

    $visibleUserIds = app(TenantContext::class)->bypass(
        'test: verifying cross-tenant bypass visibility',
        fn () => User::pluck('id')->all(),
    );

    expect($visibleUserIds)->toEqualCanonicalizing([$userA->id, $userB->id])
        // Bypass is scoped to the callback only — isolation resumes right after.
        ->and(User::pluck('id')->all())->toBe([$userA->id])
        ->and(
            AuditLog::query()
                ->where('action', 'rls.bypass')
                ->where('tenant_id', $tenantA->id)
                ->exists()
        )->toBeTrue();
});

it('never allows an UPDATE or DELETE against audit_logs, even via bypass', function () {
    [$tenantA] = createTenantWithUser();

    app(TenantContext::class)->set($tenantA->id);

    $log = app(TenantContext::class)->bypass(
        'test: attempting to tamper with an audit entry',
        fn () => AuditLog::query()->create([
            'tenant_id' => $tenantA->id,
            'action' => 'test.action',
            'created_at' => now(),
        ]),
    );

    app(TenantContext::class)->bypass('test: tamper attempt', function () use ($log) {
        expect(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'tampered']))
            ->toThrow(QueryException::class);

        expect(fn () => DB::table('audit_logs')->where('id', $log->id)->delete())
            ->toThrow(QueryException::class);
    });
});
