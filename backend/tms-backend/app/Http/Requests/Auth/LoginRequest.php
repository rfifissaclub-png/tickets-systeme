<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;

class LoginRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        // Login is inherently unauthenticated — anyone may attempt it.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // No `exists:` rule on tenant/email: those tables are RLS-protected
            // and this request runs pre-authentication with no tenant context
            // bound, so the rule would always fail closed. Resolution happens
            // in the controller inside TenantContext::bypass() instead, and an
            // unresolvable tenant/user is treated the same as a wrong password.
            'tenant' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
