<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

it('logs in a tenant user with the correct tenant slug, email, and password', function () {
    [$tenant, $user] = createTenantWithUser(['email' => 'agent@example.com']);

    $response = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => 'agent@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()->assertJsonPath('data.user.id', $user->id);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty()
        ->and($response->json('data.expires_at'))->not->toBeNull();
});

it('logs in a Super Admin without a tenant slug', function () {
    $superAdmin = app(TenantContext::class)->bypass(
        'test fixture setup',
        fn () => User::factory()->superAdmin()->create(['email' => 'root@tms.local']),
    );

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'root@tms.local',
        'password' => 'password',
    ]);

    $response->assertOk()->assertJsonPath('data.user.id', $superAdmin->id);
});

it('rejects a wrong password with a generic message', function () {
    [$tenant] = createTenantWithUser(['email' => 'agent2@example.com']);

    $response = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => 'agent2@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
});

it('rejects an unknown tenant slug with the same message as a wrong password', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'tenant' => 'does-not-exist',
        'email' => 'nobody@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
});

it('does not authenticate a user against a tenant they do not belong to', function () {
    createTenantWithUser(['email' => 'shared@example.com']);
    [$tenantB] = createTenantWithUser(['email' => 'someone-else@example.com']);

    $response = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenantB->slug,
        'email' => 'shared@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(422);
});

it('issues a non-expiring token when remember is true', function () {
    [$tenant] = createTenantWithUser(['email' => 'remember@example.com']);

    $response = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => 'remember@example.com',
        'password' => 'password',
        'remember' => true,
    ]);

    $response->assertOk()->assertJsonPath('data.expires_at', null);
});

it('records the logged-in user as the actor on the auth.login audit entry', function () {
    [$tenant, $user] = createTenantWithUser(['email' => 'audited-login@example.com']);

    $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => 'audited-login@example.com',
        'password' => 'password',
    ])->assertOk();

    // No Sanctum principal is ever resolved during the login request
    // itself (see LoginController's own docblock) — AuditService's default
    // Auth::id() would silently record a null actor here without
    // LogSuccessfulLogin passing it explicitly.
    $entry = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => AuditLog::query()->where('action', 'auth.login')->where('auditable_id', $user->id)->first(),
    );

    expect($entry)->not->toBeNull()
        ->and($entry->actor_id)->toBe($user->id);
});

it('rate limits repeated login attempts for the same email and IP', function () {
    [$tenant] = createTenantWithUser(['email' => 'throttle@example.com']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'tenant' => $tenant->slug,
            'email' => 'throttle@example.com',
            'password' => 'wrong',
        ]);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => 'throttle@example.com',
        'password' => 'wrong',
    ]);

    $response->assertStatus(429);
});
