<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// TicketAssignedNotification et al. broadcast on this channel (see
// User::receivesBroadcastNotificationsOn()) instead of Laravel's default
// "App.Models.User.{id}" channel — scoped to both tenant and user, and using
// plain string comparison since our primary keys are UUIDs, not
// auto-increment integers. (The scaffolded default channel casts ids to
// (int), which silently evaluates to 0 for a UUID string — every user would
// pass that check. This file replaces the default rather than keeping that
// bug around unused.)
Broadcast::channel('tenant.{tenantId}.user.{userId}', function (User $user, string $tenantId, string $userId) {
    return $user->tenant_id === $tenantId && $user->id === $userId;
});
