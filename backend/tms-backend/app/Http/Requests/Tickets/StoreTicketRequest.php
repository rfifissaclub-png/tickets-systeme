<?php

namespace App\Http\Requests\Tickets;

use App\Enums\RoleName;
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
        $isSuperAdmin = $this->user()->hasRole(RoleName::SuperAdmin->value);

        return [
            // ticket_types is a single platform-wide catalog (see
            // TicketType's own docblock), not tenant-scoped, so `exists`
            // deliberately matches any tenant's request the same way.
            'ticket_type_id' => ['required', 'uuid', 'exists:ticket_types,id'],
            // Only meaningful for a Super Admin actor, who has no tenant of
            // their own to default to — required for them, ignored for
            // everyone else (TicketService always forces their own bound
            // tenant regardless of what this says). No `exists:` check:
            // RLS would block it from ever matching even a genuinely valid
            // id, same reasoning as StoreUserRequest's own tenant_id.
            'tenant_id' => [Rule::requiredIf($isSuperAdmin), 'nullable', 'uuid'],
            // Required for a Super Admin (no tenant of their own to imply
            // a default client from — see TicketService::create()). The
            // RLS-scoped `exists` check stays for everyone else, where it
            // usefully confirms the client belongs to the actor's own
            // tenant, but is skipped for a Super Admin for the same reason
            // as tenant_id above: RLS would block it from ever matching.
            'client_id' => [
                Rule::requiredIf($isSuperAdmin),
                'uuid',
                Rule::when(! $isSuperAdmin, ['exists:users,id']),
            ],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
        ];
    }
}
