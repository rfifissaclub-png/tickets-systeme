<?php

it('defaults to English with no Accept-Language header', function () {
    [$tenant] = createTenantWithUser(['email' => 'locale-default@example.com']);

    $response = $this->postJson('/api/v1/auth/login', [
        'tenant' => $tenant->slug,
        'email' => 'locale-default@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
});

it('responds in French when Accept-Language: fr is sent', function () {
    [$tenant] = createTenantWithUser(['email' => 'locale-fr@example.com']);

    $response = $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9,en;q=0.8')
        ->postJson('/api/v1/auth/login', [
            'tenant' => $tenant->slug,
            'email' => 'locale-fr@example.com',
            'password' => 'wrong-password',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'Ces identifiants ne correspondent à aucun compte connu.');
});

it('falls back to English for an unsupported requested language', function () {
    [$tenant] = createTenantWithUser(['email' => 'locale-unsupported@example.com']);

    $response = $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')
        ->postJson('/api/v1/auth/login', [
            'tenant' => $tenant->slug,
            'email' => 'locale-unsupported@example.com',
            'password' => 'wrong-password',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
});

it('translates validation errors in French', function () {
    $response = $this->withHeader('Accept-Language', 'fr')
        ->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => '',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'Le champ adresse e-mail doit être une adresse e-mail valide.')
        ->assertJsonPath('errors.password.0', 'Le champ mot de passe est obligatoire.');
});
