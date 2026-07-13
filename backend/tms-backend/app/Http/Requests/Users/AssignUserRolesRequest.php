<?php

namespace App\Http\Requests\Users;

use App\Enums\RoleName;
use App\Http\Requests\ApiFormRequest;
use Closure;
use Illuminate\Validation\Rule;

class AssignUserRolesRequest extends ApiFormRequest
{
    /**
     * Needs the route-bound target User (self-assignment block, tenant
     * match), done in the controller via UserPolicy::manageRoles.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A full replace (sync), not an incremental add — the caller
            // sends the complete intended role set, same shape as
            // StoreTicketCommentRequest-style "here's the whole state" APIs
            // elsewhere in this app rather than separate add/remove verbs.
            // max:1, not min:1 — [] is a valid, meaningful state (strip all
            // access without deleting the account), but a user occupies
            // exactly one position in this app's model, never two at once
            // (see StoreUserRequest's identical rule for the full reasoning).
            'roles' => ['required', 'array', 'max:1'],
            'roles.*' => ['string', Rule::enum(RoleName::class), $this->guardSuperAdminGrant()],
        ];
    }

    /**
     * Only a Super Admin may even list the Super Admin role here — the
     * further "target must have no tenant" rule is enforced in
     * UserManagementService once the actual target user is loaded.
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
