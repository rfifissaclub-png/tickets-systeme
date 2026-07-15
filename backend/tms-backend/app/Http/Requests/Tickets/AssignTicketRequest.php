<?php

namespace App\Http\Requests\Tickets;

use App\Enums\RoleName;
use App\Http\Requests\ApiFormRequest;
use App\Models\User;
use Closure;
use Illuminate\Validation\Rule;

class AssignTicketRequest extends ApiFormRequest
{
    private const ASSIGNABLE_ROLES = [
        RoleName::SupportAgent->value,
        RoleName::Manager->value,
        RoleName::TenantAdmin->value,
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // A Super Admin has no tenant bound at all, so both the `exists`
        // check and the closure's own lookup below would be blocked by RLS
        // regardless of whether $value is a genuinely valid agent —
        // deferred to TicketService::assign() instead, the one place that
        // runs inside the cross-tenant bypass TicketAssignmentController
        // wraps this in (see its own comment for why).
        $isSuperAdmin = $this->user()->hasRole(RoleName::SuperAdmin->value);

        return [
            'agent_id' => [
                'required', 'uuid',
                Rule::when(! $isSuperAdmin, ['exists:users,id']),
                function (string $attribute, mixed $value, Closure $fail) use ($isSuperAdmin) {
                    if ($isSuperAdmin) {
                        return;
                    }

                    $user = User::query()->find($value);

                    if ($user !== null && ! $user->hasAnyRole(self::ASSIGNABLE_ROLES)) {
                        $fail('The selected user is not an assignable agent.');
                    }
                },
            ],
        ];
    }
}
