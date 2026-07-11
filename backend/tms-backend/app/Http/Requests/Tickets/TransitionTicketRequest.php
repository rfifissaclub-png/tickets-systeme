<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class TransitionTicketRequest extends ApiFormRequest
{
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
            'status' => ['required', Rule::enum(TicketStatus::class)],
        ];
    }
}
