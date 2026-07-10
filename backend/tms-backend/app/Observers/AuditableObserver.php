<?php

namespace App\Observers;

use App\Services\Audit\AuditService;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function __construct(private readonly AuditService $auditService) {}

    public function created(Model $model): void
    {
        $this->auditService->log('created', $model, newValues: $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $this->auditService->log('updated', $model, oldValues: $model->getOriginal(), newValues: $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->auditService->log('deleted', $model, oldValues: $model->getAttributes());
    }
}
