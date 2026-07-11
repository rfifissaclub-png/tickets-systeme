<?php

namespace App\Notifications\Channels;

use App\Models\Notification as TenantNotification;
use App\Models\User;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * Mirrors Laravel's built-in DatabaseChannel but writes to our tenant- and
 * recipient-scoped `notifications` table (see the
 * enable_row_level_security_for_attachments_and_notifications migration)
 * instead of assuming the stock polymorphic-only notifications table.
 *
 * Referenced directly by class name in a notification's via(), e.g.
 * `return [TenantDatabaseChannel::class, 'mail'];` — Laravel resolves any
 * unrecognized channel string as a container binding and calls ->send() on
 * it, no alias registration needed.
 */
class TenantDatabaseChannel
{
    public function send(mixed $notifiable, Notification $notification): TenantNotification
    {
        if (! $notifiable instanceof User) {
            throw new RuntimeException('TenantDatabaseChannel only supports App\Models\User notifiables.');
        }

        if ($notifiable->tenant_id === null) {
            throw new RuntimeException('Cannot deliver a tenant-scoped notification to a user with no tenant.');
        }

        $data = method_exists($notification, 'toTenantDatabase')
            ? $notification->toTenantDatabase($notifiable)
            : $notification->toArray($notifiable);

        return TenantNotification::query()->create([
            'tenant_id' => $notifiable->tenant_id,
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'type' => $notification::class,
            'data' => $data,
            'read_at' => null,
        ]);
    }
}
