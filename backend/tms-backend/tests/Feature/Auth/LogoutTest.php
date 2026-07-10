<?php

it('revokes the current token on logout and it can no longer be used', function () {
    [$tenant] = createTenantWithUser(['email' => 'logout@example.com']);

    $login = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => 'logout@example.com',
        'password' => 'password',
    ])->assertOk();

    $token = $login->json('data.token');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});
