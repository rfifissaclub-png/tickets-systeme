<?php

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('verifies the email via a valid signed link', function () {
    [, $user] = createTenantWithUser(['email_verified_at' => null]);

    $url = URL::temporarySignedRoute(
        'api.v1.auth.verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
    );

    $this->getJson($url)->assertOk();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a verification link with a tampered hash', function () {
    [, $user] = createTenantWithUser(['email_verified_at' => null]);

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
