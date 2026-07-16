<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\UserAccountCreatedNotification;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('lets a Tenant Admin create a user with roles inside their own tenant', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $response = $this->withToken($token)->postJson('/api/v1/users', [
        'name' => 'New Agent',
        'email' => 'new-agent@acme.tms.local',
        'password' => 'a-strong-password',
        'roles' => [RoleName::SupportAgent->value],
    ])->assertCreated();

    expect($response->json('data.email'))->toBe('new-agent@acme.tms.local')
        ->and($response->json('data.roles'))->toBe([RoleName::SupportAgent->value]);

    $created = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => User::query()->where('email', 'new-agent@acme.tms.local')->first(),
    );
    expect($created->tenant_id)->toBe($tenant->id);
});

it('forces a new user into the Tenant Admin\'s own tenant regardless of a tenant_id in the request', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    [$otherTenant] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $response = $this->withToken($token)->postJson('/api/v1/users', [
        'tenant_id' => $otherTenant->id,
        'name' => 'Sneaky',
        'email' => 'sneaky@acme.tms.local',
        'password' => 'a-strong-password',
    ])->assertCreated();

    expect($response->json('data.tenant_id'))->toBe($tenant->id);
});

it('rejects a Tenant Admin granting the Super Admin role', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->postJson('/api/v1/users', [
        'name' => 'Wannabe Super Admin',
        'email' => 'wannabe@acme.tms.local',
        'password' => 'a-strong-password',
        'roles' => [RoleName::SuperAdmin->value],
    ])->assertStatus(422);
});

it('emails the new user their login and initial password on creation', function () {
    Notification::fake();

    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->postJson('/api/v1/users', [
        'name' => 'New Agent',
        'email' => 'new-agent@acme.tms.local',
        'password' => 'a-strong-password',
    ])->assertCreated();

    $created = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => User::query()->where('email', 'new-agent@acme.tms.local')->first(),
    );

    Notification::assertSentTo(
        $created,
        UserAccountCreatedNotification::class,
        function (UserAccountCreatedNotification $notification) use ($created, $tenant) {
            $rendered = $notification->toMail($created)->render();

            // The login form resolves a user by (workspace slug, email) — a
            // tenant-scoped user has no way to sign in without knowing this
            // slug, so it must be in the same email as the password.
            return str_contains($rendered, 'a-strong-password')
                && str_contains($rendered, $tenant->slug);
        },
    );
});

it('omits the workspace line from the welcome email for a platform-level (Super Admin) account', function () {
    Notification::fake();

    $superAdmin = createSuperAdmin();
    $superAdminToken = loginAs('', $superAdmin->email);

    $this->withToken($superAdminToken)->postJson('/api/v1/users', [
        'name' => 'Second Super Admin',
        'email' => 'second-super-admin@tms.local',
        'password' => 'a-strong-password',
    ])->assertCreated();

    $created = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => User::query()->where('email', 'second-super-admin@tms.local')->first(),
    );

    Notification::assertSentTo(
        $created,
        UserAccountCreatedNotification::class,
        fn (UserAccountCreatedNotification $notification) => ! str_contains(
            $notification->toMail($created)->render(),
            'Workspace:',
        ),
    );
});

it('lets a Super Admin list one tenant\'s users, with their roles correctly resolved', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $response = $this->withToken($token)->getJson("/api/v1/users?tenant_id={$tenant->id}")->assertOk();

    $listed = collect($response->json('data'))->firstWhere('id', $tenantAdmin->id);

    // Spatie's team-scoped model_has_roles pivot is a separate mechanism
    // from the RLS bypass a Super Admin's platform-wide listing needs —
    // without also binding the filtered tenant's own team id, this comes
    // back an empty array for every row despite each user genuinely
    // holding a role.
    expect($listed['roles'])->toBe([RoleName::TenantAdmin->value]);
});

it('lets a Super Admin list users across every tenant at once, with each row\'s own roles resolved', function () {
    [$tenantA, $adminA] = createTenantWithUser();
    app(TenantContext::class)->set($tenantA->id);
    $adminA->assignRole(RoleName::TenantAdmin->value);

    [$tenantB, $adminB] = createTenantWithUser();
    app(TenantContext::class)->set($tenantB->id);
    $adminB->assignRole(RoleName::Manager->value);

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $response = $this->withToken($token)->getJson('/api/v1/users?per_page=100')->assertOk();
    $byId = collect($response->json('data'))->keyBy('id');

    expect($byId[$adminA->id]['roles'])->toBe([RoleName::TenantAdmin->value])
        ->and($byId[$adminB->id]['roles'])->toBe([RoleName::Manager->value]);
});

it('lets a Super Admin create a user in another tenant with the role correctly reflected in the response', function () {
    [$tenant] = createTenantWithUser();
    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $response = $this->withToken($token)->postJson('/api/v1/users', [
        'tenant_id' => $tenant->id,
        'name' => 'Cross Tenant Agent',
        'email' => 'cross-tenant-agent@example.com',
        'password' => 'a-strong-password',
        'roles' => [RoleName::SupportAgent->value],
    ])->assertCreated();

    expect($response->json('data.roles'))->toBe([RoleName::SupportAgent->value]);
});

it('lets a Super Admin view and update a cross-tenant user with roles correctly reflected', function () {
    [$tenant, $target] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $target->assignRole(RoleName::SupportAgent->value);

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)->getJson("/api/v1/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.roles', [RoleName::SupportAgent->value]);

    $this->withToken($token)->patchJson("/api/v1/users/{$target->id}", ['name' => 'Renamed By Super Admin'])
        ->assertOk()
        ->assertJsonPath('data.roles', [RoleName::SupportAgent->value])
        ->assertJsonPath('data.name', 'Renamed By Super Admin');
});

it('lets a Super Admin sync roles for a cross-tenant user with the response reflecting the change', function () {
    [$tenant, $target] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $target->assignRole(RoleName::SupportAgent->value);

    $superAdmin = createSuperAdmin();
    $token = loginAs('', $superAdmin->email);

    $this->withToken($token)
        ->patchJson("/api/v1/users/{$target->id}/roles", ['roles' => [RoleName::Manager->value]])
        ->assertOk()
        ->assertJsonPath('data.roles', [RoleName::Manager->value]);
});

it('rejects creating a user with more than one role at once', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->postJson('/api/v1/users', [
        'name' => 'Double Role',
        'email' => 'double-role@acme.tms.local',
        'password' => 'a-strong-password',
        'roles' => [RoleName::Manager->value, RoleName::SupportAgent->value],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('roles');
});

it('rejects assigning more than one role to an existing user at once', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $agent = User::factory()->for($tenant)->create();
    $agent->assignRole(RoleName::SupportAgent->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->patchJson("/api/v1/users/{$agent->id}/roles", [
        'roles' => [RoleName::Manager->value, RoleName::SupportAgent->value],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('roles');
});

it('rejects a duplicate email within the same tenant but allows it across two different tenants', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);
    $existing = User::factory()->for($tenant)->create(['email' => 'shared@example.com']);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->postJson('/api/v1/users', [
        'name' => 'Duplicate',
        'email' => $existing->email,
        'password' => 'a-strong-password',
    ])->assertStatus(422)->assertJsonPath('errors.email.0', fn ($message) => is_string($message));

    // A Super Admin creating a user with the same email in a *different*
    // tenant must succeed: uniqueness is scoped per tenant, not global.
    $superAdmin = createSuperAdmin();
    [$otherTenant] = createTenantWithUser();
    $superAdminToken = loginAs('', $superAdmin->email);

    $this->withToken($superAdminToken)->postJson('/api/v1/users', [
        'tenant_id' => $otherTenant->id,
        'name' => 'Same Email Different Tenant',
        'email' => $existing->email,
        'password' => 'a-strong-password',
    ])->assertCreated();
});

it('lets a Tenant Admin update roles for a user in their tenant but not for themselves', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);
    $member = User::factory()->for($tenant)->create();
    $member->assignRole(RoleName::SupportAgent->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)
        ->patchJson("/api/v1/users/{$member->id}/roles", ['roles' => [RoleName::Manager->value]])
        ->assertOk()
        ->assertJsonPath('data.roles', [RoleName::Manager->value]);

    // Self-role-assignment is blocked even for a Tenant Admin — see
    // UserPolicy::manageRoles().
    $this->withToken($token)
        ->patchJson("/api/v1/users/{$tenantAdmin->id}/roles", ['roles' => [RoleName::Manager->value]])
        ->assertForbidden();
});

it('prevents a Tenant Admin from managing users in another tenant', function () {
    [$tenant, $tenantAdmin] = createTenantWithUser();
    [$otherTenant, $otherUser] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    $this->withToken($token)->getJson("/api/v1/users/{$otherUser->id}")->assertNotFound();
});
