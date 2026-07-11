<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\ApiFormRequest;

class UpdateUserRequest extends ApiFormRequest
{
    /**
     * Needs the route-bound User instance (self-edit vs. users.update),
     * done in the controller via UserPolicy.
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
            'email' => ['sometimes', 'string', 'email', 'max:255'],
        ];
    }
}
