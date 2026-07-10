<?php

namespace App\Http\Requests;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiFormRequest extends FormRequest
{
    /**
     * Every concrete form request must default to requiring authorization to
     * be checked explicitly (via a policy) rather than silently allowing
     * every authenticated user through.
     */
    public function authorize(): bool
    {
        return false;
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                message: 'The given data was invalid.',
                status: 422,
                errors: $validator->errors()->toArray(),
                code: 'validation_failed',
            )
        );
    }
}
