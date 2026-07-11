<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Extends Laravel's own DatabaseNotification (table name, casts, markAsRead
 * etc. are all inherited) rather than reimplementing it — the only things
 * that differ from stock are UUID keys and the tenant_id column added by the
 * `notifications` migration, written by TenantDatabaseChannel.
 */
class Notification extends DatabaseNotification
{
    use HasUuids;
}
