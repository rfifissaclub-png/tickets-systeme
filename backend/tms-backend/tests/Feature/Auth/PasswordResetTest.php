<?php

use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

// Notification::fake() (used by every other test in this file) intercepts
// before toMail() ever runs, so it can't catch a bug in URL generation
// itself — which is exactly how the original bug here shipped invisibly:
// ResetPassword's default resetUrl() calls route('password.reset', ...), a
// route that doesn't exist in this API-only app, and throws. This test
// renders the real mail message (no fake) to prove
// AppServiceProvider::boot()'s ResetPassword::createUrlUsing() override
// actually prevents that.
it('builds a working reset-password mail without a "password.reset" route registered', function () {
    [$tenant, $user] = createTenantWithUser(['email' => 'mail-render@example.com']);

    // $user->tenant is a lazy relation — without tenant context bound, RLS
    // would hide the tenant row entirely and $user->tenant?->slug would
    // silently resolve to null instead of exercising the real path.
    app(TenantContext::class)->set($tenant->id);

    $notification = new ResetPassword('a-fake-token-for-url-rendering');

    $mail = $notification->toMail($user);

    expect($mail->actionUrl)
        ->toStartWith(rtrim(config('app.frontend_url'), '/').'/reset-password?')
        ->toContain('token=a-fake-token-for-url-rendering')
        ->toContain('email='.urlencode($user->email))
        ->toContain('tenant='.$tenant->slug);
});

it('always returns the same generic message for forgot-password, whether or not the account exists', function () {
    Notification::fake();

    [$tenant] = createTenantWithUser(['email' => 'exists@example.com']);

    $existing = $this->postJson('/api/v1/auth/forgot-password', [
        'tenant' => $tenant->slug,
        'email' => 'exists@example.com',
    ])->assertOk();

    $missing = $this->postJson('/api/v1/auth/forgot-password', [
        'tenant' => $tenant->slug,
        'email' => 'does-not-exist@example.com',
    ])->assertOk();

    expect($existing->json('message'))->toBe($missing->json('message'));
});

it('sends a reset link only to the user in the matching tenant', function () {
    Notification::fake();

    [$tenant, $user] = createTenantWithUser(['email' => 'reset@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', [
        'tenant' => $tenant->slug,
        'email' => 'reset@example.com',
    ])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets the password with a valid token and allows login with the new password', function () {
    Notification::fake();

    [$tenant, $user] = createTenantWithUser(['email' => 'resetme@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', [
        'tenant' => $tenant->slug,
        'email' => 'resetme@example.com',
    ])->assertOk();

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $this->postJson('/api/v1/auth/reset-password', [
        'tenant' => $tenant->slug,
        'email' => 'resetme@example.com',
        'token' => $token,
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ])->assertOk();

    $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => 'resetme@example.com',
        'password' => 'new-strong-password',
    ])->assertOk();
});

it('rejects a reset attempt with an invalid token', function () {
    [$tenant] = createTenantWithUser(['email' => 'badtoken@example.com']);

    $this->postJson('/api/v1/auth/reset-password', [
        'tenant' => $tenant->slug,
        'email' => 'badtoken@example.com',
        'token' => 'not-a-real-token',
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ])->assertStatus(422);
});
