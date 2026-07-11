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
            // Tenant-scoped automatically: RLS is already bound by the time
            // this validates, so `exists` only ever matches this tenant's
            // own ticket types (see StoreTicketRequest for the same note).
            'ticket_type_ids' => ['required', 'array'],
            'ticket_type_ids.*' => ['uuid', 'exists:ticket_types,id'],
        ];
    }
}
