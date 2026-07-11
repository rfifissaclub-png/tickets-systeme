<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketPriority;
use App\Http\Requests\ApiFormRequest;
use App\Models\Ticket;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Ticket::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Tenant-scoped automatically: RLS is already bound by the time
            // this request is validated (auth:sanctum + tenant.context run
            // first), so `exists` only ever matches rows in this tenant.
            'ticket_type_id' => ['required', 'uuid', 'exists:ticket_types,id'],
            'client_id' => ['sometimes', 'uuid', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
        ];
    }
}
