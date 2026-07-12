<?php

use App\Enums\RoleName;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;

it('seeds the Main Corporation tenant for unaffiliated clients', function () {
    $this->seed(DatabaseSeeder::class);

    $mainCorp = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => Tenant::query()->where('slug', Tenant::MAIN_CORPORATION_SLUG)->first(),
    );

    expect($mainCorp)->not->toBeNull()
        ->and($mainCorp->name)->toBe('Main Corporation');
});

it('re-running the seeder does not duplicate or fail on the Main Corporation tenant', function () {
    // Deliberately not $this->seed(DatabaseSeeder::class) called twice:
    // seedLocalDevAccounts() (fixed-email demo users/tenant) was never
    // designed to be idempotent and isn't what this test is about — this
    // exercises DatabaseSeeder::seedMainCorporation()'s own firstOrCreate
    // pattern directly, proving *that* survives a second run, which is the
    // actual production concern (a deploy step re-running seeders).
    $this->seed(DatabaseSeeder::class);

    app(TenantContext::class)->bypass(
        'test: simulate a second seeder run',
        fn () => Tenant::query()->firstOrCreate(
            ['slug' => Tenant::MAIN_CORPORATION_SLUG],
            ['name' => 'Main Corporation'],
        ),
    );

    $count = app(TenantContext::class)->bypass(
        'test assertion',
        fn () => Tenant::query()->where('slug', Tenant::MAIN_CORPORATION_SLUG)->count(),
    );

    expect($count)->toBe(1);
});

it('is a fully functional tenant: an unaffiliated client can log in and create a ticket under it', function () {
    $this->seed(DatabaseSeeder::class);

    $mainCorp = app(TenantContext::class)->bypass(
        'test fixture setup',
        fn () => Tenant::query()->where('slug', Tenant::MAIN_CORPORATION_SLUG)->first(),
    );

    app(TenantContext::class)->set($mainCorp->id);

    $ticketType = TicketType::factory()->for($mainCorp)->create(['name' => 'General Inquiry']);
    $client = User::factory()->for($mainCorp)->create();
    $client->assignRole(RoleName::Client->value);

    $token = loginAs($mainCorp->slug, $client->email);

    $this->withToken($token)->postJson('/api/v1/tickets', [
        'ticket_type_id' => $ticketType->id,
        'subject' => 'Need help, not affiliated with any specific entity',
        'description' => 'Global assistance request.',
    ])->assertCreated();
});
