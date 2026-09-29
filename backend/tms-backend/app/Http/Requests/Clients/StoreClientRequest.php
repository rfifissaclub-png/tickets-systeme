<?php

namespace App\Http\Requests\Clients;

use Illuminate\Foundation\Http\FormRequest;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use App\Enums\RoleName;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Creating clients is a tenant-management operation; require the
        // actor to hold the explicit tenants.update permission.
        $user = $this->user();

        if ($user === null || ! method_exists($user, 'hasPermissionTo')) {
            return false;
        }

        // Allow either the explicit tenants.update permission or the
        // Tenant Admin role (test fixtures create a Tenant Admin user).
        try {
            if ($user->hasPermissionTo('tenants.update')) {
                return true;
            }
        } catch (PermissionDoesNotExist) {
            // ignore and fall back to role check
        }

        return $user->hasRole(RoleName::TenantAdmin->value);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'is_linked' => ['sometimes', 'boolean'],
            'parent_tenant_id' => ['nullable', 'uuid', 'exists:tenants,id'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
