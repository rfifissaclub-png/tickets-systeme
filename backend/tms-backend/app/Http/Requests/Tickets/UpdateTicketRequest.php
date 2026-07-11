<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketPriority;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends ApiFormRequest
{
    /**
     * Authorization needs the route-bound Ticket instance (ownership +
     * read-only checks), so it's done in the controller via $this->authorize()
     * rather than here — this request only validates field shape.
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
            'ticket_type_id' => ['sometimes', 'uuid', 'exists:ticket_types,id'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
        ];
    }
}
