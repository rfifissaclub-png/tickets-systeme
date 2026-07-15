<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;

class AssignAgentSkillsRequest extends ApiFormRequest
{
    /**
     * Needs the route-bound target User, done in the controller via
     * UserPolicy::manageSkills.
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
            // A full replace (sync) of the agent's qualified ticket types,
            // same "send the whole state" shape as AssignUserRolesRequest.
            // ticket_types is a global catalog (see TicketType's own
            // docblock), so `exists` deliberately isn't tenant-scoped —
            // any ticket type is a valid skill for any agent.
            'ticket_type_ids' => ['required', 'array'],
            'ticket_type_ids.*' => ['uuid', 'exists:ticket_types,id'],
        ];
    }
}
