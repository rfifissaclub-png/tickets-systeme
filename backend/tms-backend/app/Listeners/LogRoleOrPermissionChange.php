<?php

namespace App\Listeners;

use App\Services\Audit\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

/**
 * Spatie's role/permission attach/detach events (enabled via
 * permission.events_enabled) are the only hook into grants and revokes —
 * HasRoles/HasPermissions don't otherwise go through AuditableObserver.
 */
class LogRoleOrPermissionChange
{
    public function __construct(private readonly AuditService $auditService) {}

    public function handleRoleAttached(RoleAttachedEvent $event): void
    {
        $this->log('role.attached', $event->model, $event->rolesOrIds);
    }

    public function handleRoleDetached(RoleDetachedEvent $event): void
    {
        $this->log('role.detached', $event->model, $event->rolesOrIds);
    }

    public function handlePermissionAttached(PermissionAttachedEvent $event): void
    {
        $this->log('permission.attached', $event->model, $event->permissionsOrIds);
    }

    public function handlePermissionDetached(PermissionDetachedEvent $event): void
    {
        $this->log('permission.detached', $event->model, $event->permissionsOrIds);
    }

    private function log(string $action, Model $model, mixed $rolesOrPermissionsOrIds): void
    {
        $this->auditService->log($action, $model, newValues: [
            'roles_or_permissions' => $this->normalize($rolesOrPermissionsOrIds),
        ]);
    }

    /**
     * @return list<mixed>
     */
    private function normalize(mixed $value): array
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        return array_values(array_map(
            fn ($item) => is_object($item) && method_exists($item, 'getAttribute') ? ($item->name ?? $item->getKey()) : $item,
            $value,
        ));
    }
}
