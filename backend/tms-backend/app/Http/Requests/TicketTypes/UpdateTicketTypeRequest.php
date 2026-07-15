<?php

namespace App\Http\Requests\TicketTypes;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketTypeRequest extends ApiFormRequest
{
    /**
     * Needs the route-bound TicketType instance — done in the controller.
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
            // ignore($this->route('ticketType')): the route parameter is
            // already the resolved model by the time a FormRequest
            // validates (SubstituteBindings runs first) — excludes this
            // same row from the uniqueness check.
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('ticket_types', 'name')->ignore($this->route('ticketType'))],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
