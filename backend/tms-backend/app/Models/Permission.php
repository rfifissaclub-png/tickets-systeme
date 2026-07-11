<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Spatie's stock Permission model has no idea the `permissions` table uses a
 * UUID primary key (every table in this app does) — without this override,
 * Eloquent defaults to treating `id` as an auto-incrementing integer, which
 * silently corrupts every relationship query built from a model instance's
 * key (e.g. `role_has_permissions.permission_id in (?)` gets bound an
 * integer instead of the real UUID). Only ever surfaced against a real
 * Postgres connection — see RoleAndPermissionSeeder's first live run.
 */
class Permission extends SpatiePermission
{
    use HasUuids;
}
