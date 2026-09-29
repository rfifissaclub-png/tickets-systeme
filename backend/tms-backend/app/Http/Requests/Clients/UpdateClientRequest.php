<?php

namespace App\Http\Requests\Clients;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() != null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'is_linked' => ['sometimes', 'boolean'],
            'parent_tenant_id' => ['nullable', 'uuid', 'exists:tenants,id'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
