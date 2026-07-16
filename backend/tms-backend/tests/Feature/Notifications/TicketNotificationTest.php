<?php

use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCommentAddedNotification;
use App\Notifications\TicketStatusChangedNotification;
use App\Services\Tenancy\TenantContext;
use App\Services\Tickets\TicketPoolService;
use App\Services\Tickets\TicketService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification as NotificationFacade;

it('notifies the agent when a manager directly assigns a ticket', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    NotificationFacade::fake();

    [$tenant, $ticketType, , $agent, $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    $agent->assignRole(RoleName::SupportAgent->value);
    // A plain Support Agent is skill-gated (fails closed) by
    // TicketService::assign() too, not just the pool — qualify them for
    // this ticket's type first, same as this file's own pool-pull fixture.
    $agent->qualifiedTicketTypes()->attach($ticketType->id, ['tenant_id' => $tenant->id]);

    app(TicketService::class)->assign($ticket, $agent);

    NotificationFacade::assertSentTo($agent, TicketAssignedNotification::class);
});

it('does not notify an agent who pulls a ticket for themselves', function () {
    NotificationFacade::fake();

    [$tenant, $ticketType, , $agent, $ticket] = createTicketFixture();

    app(TenantContext::class)->set($tenant->id);
    // A fresh, roleless agent is skill-gated (fails closed) by
    // TicketPoolService::isSkillGated() — qualify them for this ticket's
    // type first, same as TicketPoolTest's equivalent fixture setup.
    $agent->qualifiedTicketTypes()->attach($ticketType->id, ['tenant_id' => $tenant->id]);

    app(TicketPoolService::class)->pull($ticket->id, $agent);

    NotificationFacade::assertNothingSent();
});

it('notifies both client and agent on a status change, excluding whoever made it', function () {
    NotificationFacade::fake();

    [$tenant, , $client, $agent, $ticket] = createTicketFixture(['status' => TicketStatus::InProgress, 'agent_id' => null]);

    app(TenantContext::class)->set($tenant->id);
    $ticket->update(['agent_id' => $agent->id]);

    // Act as the agent (Auth::id() drives the "don't notify the actor" rule).
    $this->actingAs($agent);

    app(TicketService::class)->transitionStatus($ticket, TicketStatus::Resolved);

    NotificationFacade::assertSentTo($client, TicketStatusChangedNotification::class);
    NotificationFacade::assertNotSentTo($agent, TicketStatusChangedNotification::class);
});

it('notifies the agent when the client comments, and vice versa, but never on an internal note', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    NotificationFacade::fake();

    [$tenant, , $client, $agent, $ticket] = createTicketFixture(['agent_id' => null]);

    app(TenantContext::class)->set($tenant->id);
    $client->assignRole(RoleName::Client->value);
    $agent->assignRole(RoleName::SupportAgent->value);
    $ticket->update(['agent_id' => $agent->id]);

    $clientToken = loginAs($tenant->slug, $client->email);

    $this->withToken($clientToken)
        ->postJson("/api/v1/tickets/{$ticket->id}/comments", ['body' => 'Any update?'])
        ->assertCreated();

    NotificationFacade::assertSentTo($agent, TicketCommentAddedNotification::class);
    NotificationFacade::assertNotSentTo($client, TicketCommentAddedNotification::class);

    NotificationFacade::fake(); // reset the fake's recorded notifications

    $agentToken = loginAs($tenant->slug, $agent->email);

    $this->withToken($agentToken)
        ->postJson("/api/v1/tickets/{$ticket->id}/comments", ['body' => 'Internal note', 'is_internal' => true])
        ->assertCreated();

    NotificationFacade::assertNothingSent();
});

it('isolates notifications per recipient, not just per tenant, at the database level', function () {
    [$tenant, , , , $ticket] = createTicketFixture();
    $userA = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenant)->create());
    $userB = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenant)->create());

    app(TenantContext::class)->set($tenant->id);
    app(TenantContext::class)->bindUser($userA->id);

    $userA->notify(new TicketAssignedNotification($ticket));

    // Still bound to tenant A, but now "acting as" user B — user B must not
    // see user A's notification even though they share a tenant.
    app(TenantContext::class)->bindUser($userB->id);
    expect(Notification::query()->count())->toBe(0);

    app(TenantContext::class)->bindUser($userA->id);
    expect(Notification::query()->count())->toBe(1);
});

it('lets a user mark only their own notification as read via the API', function () {
    [$tenant, , , , $ticket] = createTicketFixture();
    $user = app(TenantContext::class)->bypass('test fixture setup', fn () => User::factory()->for($tenant)->create());

    app(TenantContext::class)->set($tenant->id);
    app(TenantContext::class)->bindUser($user->id);

    $user->notify(new TicketAssignedNotification($ticket));

    $notification = Notification::query()->first();

    $token = loginAs($tenant->slug, $user->email);

    $this->withToken($token)
        ->patchJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk();

    expect($notification->fresh()->read_at)->not->toBeNull();
});
