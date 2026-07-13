<?php

namespace App\Http\Requests\Users;

use App\Enums\RoleName;
use App\Http\Requests\ApiFormRequest;
use App\Models\User;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Only meaningful for a Super Admin actor (creating a user in an
            // arbitrary tenant, or omitting this entirely to create another
            // platform-level Super Admin); UserManagementService ignores it
            // for a Tenant Admin actor and forces their own bound tenant
            // instead — a Tenant Admin can never provision a user outside
            // their own tenant regardless of what this field says.
            'tenant_id' => ['sometimes', 'nullable', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
            // max:1 — a user occupies exactly one position in this app's
            // model (Tenant Admin, Manager, Support Agent, or Client); nothing
            // in the permission/policy layer is designed for one actor
            // holding two of these at once, and stacking them would blend
            // capabilities that are deliberately kept separate elsewhere
            // (e.g. a Client with Support Agent's tickets.pull would break
            // the ownership-only assumption the Client side relies on).
            'roles' => ['sometimes', 'array', 'max:1'],
            'roles.*' => ['string', Rule::enum(RoleName::class), $this->guardSuperAdminGrant()],
        ];
    }

    /**
     * Only a Super Admin may even list the Super Admin role here. The
     * further rule that it can only land on a platform-level account (no
     * tenant) is enforced in UserManagementService, once the target user's
     * actual tenant_id is known — not derivable yet at this point for the
     * roles.* update path (AssignUserRolesRequest), where the target is
     * just a route id, not a resolved model.
     */
    private function guardSuperAdminGrant(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($value === RoleName::SuperAdmin->value && ! $this->user()->hasRole(RoleName::SuperAdmin->value)) {
                $fail(__('messages.users.only_super_admin_grants_super_admin'));
            }
        };
    }
}
