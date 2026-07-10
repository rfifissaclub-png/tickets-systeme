<?php

namespace App\Listeners;

use App\Services\Audit\AuditService;
use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    public function __construct(private readonly AuditService $auditService) {}

    public function handle(Logout $event): void
    {
        $this->auditService->log('auth.logout', $event->user);
    }
}
