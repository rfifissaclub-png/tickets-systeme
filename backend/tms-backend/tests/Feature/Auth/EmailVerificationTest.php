<?php

use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

// Notification::fake() (used elsewhere in this file) intercepts before
// toMail() ever runs, so it can't catch a bug in URL generation itself —
// same reasoning as PasswordResetTest's equivalent test. This renders the
// real mail message to prove AppServiceProvider::boot()'s
// VerifyEmail::createUrlUsing() override actually points at the frontend,
// not the raw API endpoint (which would show the user bare JSON).
it('builds a frontend-facing verification URL carrying the same id/hash/expires/signature the API validates', function () {
    [$tenant, $user] = createTenantWithUser(['email_verified_at' => null]);
    app(TenantContext::class)->set($tenant->id);

    $mail = (new VerifyEmail)->toMail($user);

    expect($mail->actionUrl)
        ->toStartWith(rtrim(config('app.frontend_url'), '/').'/verify-email?')
        ->toContain('id='.$user->id)
        ->toContain('hash='.sha1($user->getEmailForVerification()))
        ->toContain('expires=')
        ->toContain('signature=');

    // The frontend page's whole job is to replay these four query params
    // against the real API endpoint (see src/api/auth.ts's verifyEmail) —
    // prove that round-trip actually succeeds, not just that the URL looks
    // right.
    parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);

    $this->getJson("/api/v1/auth/email/verify/{$query['id']}/{$query['hash']}?expires={$query['expires']}&signature={$query['signature']}")
        ->assertOk();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('verifies the email via a valid signed link', function () {
    [$tenant, $user] = createTenantWithUser(['email_verified_at' => null]);
    app(TenantContext::class)->set($tenant->id);

    $url = URL::temporarySignedRoute(
        'api.v1.auth.verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
    );

    $this->getJson($url)->assertOk();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a verification link with a tampered hash', function () {
    [$tenant, $user] = createTenantWithUser(['email_verified_at' => null]);
    app(TenantContext::class)->set($tenant->id);

    $url = URL::temporarySignedRoute(
        'api.v1.auth.verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('someone-else@example.com')],
    );

    $this->getJson($url)->assertStatus(403);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects a verification link once its signature has expired', function () {
    [, $user] = createTenantWithUser(['email_verified_at' => null]);

    $url = URL::temporarySignedRoute(
        'api.v1.auth.verification.verify',
        now()->subMinutes(1),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
    );

    $this->getJson($url)->assertStatus(403);
});

it('resends the verification notification for an unverified authenticated user', function () {
    Notification::fake();

    [$tenant, $user] = createTenantWithUser(['email_verified_at' => null]);

    $login = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $this->withToken($login->json('data.token'))
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertOk();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does not resend the verification notification if already verified', function () {
    Notification::fake();

    // Factory default is already verified (email_verified_at => now()).
    [$tenant, $user] = createTenantWithUser();

    $login = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $this->withToken($login->json('data.token'))
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertOk();

    Notification::assertNotSentTo($user, VerifyEmail::class);
});
