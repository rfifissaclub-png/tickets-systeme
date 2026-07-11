<?php

namespace App\Http\Requests\TicketTypes;

use App\Http\Requests\ApiFormRequest;

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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
