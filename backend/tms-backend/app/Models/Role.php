<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Same reasoning as App\Models\Permission — the `roles` table is UUID-keyed
 * like every table in this app, and Spatie's stock Role model doesn't know
 * that without this override.
 */
class Role extends SpatieRole
{
    use HasUuids;
}
