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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
