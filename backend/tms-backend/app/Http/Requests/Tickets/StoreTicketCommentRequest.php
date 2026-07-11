<?php

namespace App\Http\Requests\Tickets;

use App\Http\Requests\ApiFormRequest;

class StoreTicketCommentRequest extends ApiFormRequest
{
    /**
     * Authorization needs the route-bound Ticket (participant/permission
     * checks, is_internal gating), so it's done in the controller.
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
            'body' => ['required', 'string'],
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }
}
