<?php

namespace App\Concerns;

use App\Observers\AuditableObserver;

/**
 * Opt a model into automatic create/update/delete audit logging via
 * AuditableObserver. Applied explicitly per model (User for now) rather than
 * globally, so audit coverage is a deliberate choice per model, not an
 * assumption.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditableObserver::class);
    }
}
