<?php

namespace App\Http\Requests\Tenants;

use App\Enums\TenantStatus;
use App\Http\Requests\ApiFormRequest;
use App\Models\Tenant;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Tenant::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Uniqueness isn't checked here: the tenants table is itself
            // RLS-protected (policy predicate is `id = current tenant`), so
            // a plain `unique:tenants,slug` rule would run under whatever
            // session is bound — for the Super-Admin-only caller of this
            // request that's normally unbound and would see zero rows,
            // never actually catching a collision. TenantManagementService
            // checks it inside an explicit bypass instead.
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash'],
            'status' => ['sometimes', Rule::enum(TenantStatus::class)],
        ];
    }
}
