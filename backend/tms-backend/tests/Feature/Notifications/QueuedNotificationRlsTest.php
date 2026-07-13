<?php

use App\Enums\RoleName;
use App\Notifications\UserAccountCreatedNotification;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

/**
 * Regression test for a bug that only ever manifests against a real queue
 * worker, never under the default `QUEUE_CONNECTION=sync` test setup (see
 * phpunit.xml): the worker is a single long-lived process reusing one
 * Postgres connection across every job, which starts with no
 * app.tenant_id/app.bypass_rls bound at all. Without
 * AppServiceProvider::configureQueueRlsBypass(), RLS silently empties the
 * notifiable collection Illuminate\Notifications\SendQueuedNotifications
 * restores from the queued payload (Collection::get(), not firstOrFail()) —
 * the job reports success and no notification is ever sent, with no
 * exception anywhere. This test uses the real `database` queue driver and
 * actually runs the worker once, after deliberately stripping the tenant
 * context the rest of this test suite would otherwise leave bound — that
 * binding is exactly what would mask the bug.
 */
it('delivers a queued notification even though the queue worker starts with no tenant context bound', function () {
    config(['queue.default' => 'database']);

    [$tenant, $tenantAdmin] = createTenantWithUser();
    app(TenantContext::class)->set($tenant->id);
    $tenantAdmin->assignRole(RoleName::TenantAdmin->value);

    $token = loginAs($tenant->slug, $tenantAdmin->email);

    Event::fake([NotificationSent::class]);

    $this->withToken($token)->postJson('/api/v1/users', [
        'name' => 'Queued Notify Test',
        'email' => 'queued-notify@acme.tms.local',
        'password' => 'a-strong-password',
    ])->assertCreated();

    // Simulate a fresh queue-worker connection: no tenant bound, no bypass.
    // Inheriting this test's own bound context here would hide the bug
    // entirely, the same way it stayed invisible until run against the real
    // Docker Compose stack's `queue` service.
    DB::statement('RESET app.tenant_id');
    DB::statement('RESET app.bypass_rls');

    Artisan::call('queue:work', [
        '--queue' => 'default',
        '--once' => true,
        '--stop-when-empty' => true,
    ]);

    Event::assertDispatched(
        NotificationSent::class,
        fn ($event) => $event->notification instanceof UserAccountCreatedNotification,
    );
});
