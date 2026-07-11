<?php

namespace App\Http\Requests\Tickets;

use App\Enums\RoleName;
use App\Http\Requests\ApiFormRequest;
use App\Models\User;
use Closure;

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
        return [
            'agent_id' => [
                'required', 'uuid', 'exists:users,id',
                function (string $attribute, mixed $value, Closure $fail) {
                    $user = User::query()->find($value);

                    if ($user !== null && ! $user->hasAnyRole(self::ASSIGNABLE_ROLES)) {
                        $fail('The selected user is not an assignable agent.');
                    }
                },
            ],
        ];
    }
}
