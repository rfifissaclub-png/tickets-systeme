<?php

namespace App\Http\Requests\TicketTypes;

use App\Http\Requests\ApiFormRequest;
use App\Models\TicketType;

class StoreTicketTypeRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', TicketType::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Unique platform-wide now (a single global catalog — see
            // TicketType's own docblock), not per-tenant, so a plain
            // `unique:ticket_types,name` is enough; no scoping column left
            // to add a `->where(...)` clause for.
            'name' => ['required', 'string', 'max:255', 'unique:ticket_types,name'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
