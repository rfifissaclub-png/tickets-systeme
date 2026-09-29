<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketPriority;
use App\Enums\RoleName;
use App\Http\Requests\ApiFormRequest;
use App\Services\Tenancy\TenantContext;
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
        $rules = [
            'ticket_type_id' => ['sometimes', 'uuid', 'exists:ticket_types,id'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
        ];

        // If the ticket belongs to a different tenant/session than the
        // actor's current tenant context, lock down core fields — they
        // must not be changeable (only `ticket_type_id` and `priority`
        // remain editable). This defends even direct API calls.
        $ticket = $this->route('ticket');

        // Super Admins operate at platform level and must be allowed to
        // edit cross-tenant resources; skip the prohibition for them.
        $user = $this->user();
        if ($user?->hasRole(RoleName::SuperAdmin->value)) {
            return $rules;
        }

        $currentTenantId = app(TenantContext::class)->id();

        if ($ticket !== null && $ticket->tenant_id !== $currentTenantId) {
            // Prohibit presence of these fields in the payload.
            $rules['subject'] = ['prohibited'];
            $rules['description'] = ['prohibited'];
        }

        return $rules;
    }
}
