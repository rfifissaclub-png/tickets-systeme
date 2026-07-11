<?php

namespace App\Http\Requests\Tenants;

use App\Enums\TenantStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends ApiFormRequest
{
    /**
     * Needs the route-bound Tenant instance (own-tenant vs. platform-wide
     * check), done in the controller via TenantPolicy.
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
            // Slug is deliberately not updatable here: it's embedded in the
            // login flow (tenant slug disambiguates users across tenants)
            // and in already-sent password-reset links — changing it out
            // from under either would break in-flight flows silently.
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(TenantStatus::class)],
        ];
    }
}
