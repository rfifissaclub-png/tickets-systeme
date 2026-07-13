<?php

namespace App\Listeners;

use App\Services\Audit\AuditService;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function __construct(private readonly AuditService $auditService) {}

    public function handle(Login $event): void
    {
        // This event is dispatched from inside LoginController before any
        // Sanctum guard has resolved a principal for the request (see its
        // own docblock on why it's dispatched manually) — AuditService's
        // default Auth::id() would be null here, silently dropping the
        // actor from the single audit entry that exists specifically to
        // record who logged in.
        $this->auditService->log('auth.login', $event->user, actorId: $event->user->getKey());
    }
}
