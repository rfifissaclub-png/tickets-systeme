<?php

namespace App\Listeners;

use App\Services\Audit\AuditService;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function __construct(private readonly AuditService $auditService) {}

    public function handle(Login $event): void
    {
        $this->auditService->log('auth.login', $event->user);
    }
}
