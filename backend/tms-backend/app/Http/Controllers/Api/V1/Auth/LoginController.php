<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\TenantAwareUserResolver;
use App\Services\Tenancy\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __invoke(
        LoginRequest $request,
        TenantAwareUserResolver $resolver,
        TenantContext $tenantContext,
    ): JsonResponse {
        $credentials = $request->validated();

        $user = $resolver->findUser($credentials['tenant'] ?? null, $credentials['email']);

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            // Same message either way: don't let the response distinguish
            // "no such user" from "wrong password" (account enumeration).
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->tenant_id !== null) {
            $tenantContext->set($user->tenant_id);
        }

        Auth::login($user);

        $remember = (bool) ($credentials['remember'] ?? false);
        // config('sanctum.expiration', 1440)'s default only applies if the
        // key is missing, not if it's explicitly null — coalesce instead.
        $expiresAt = $remember ? null : now()->addMinutes(config('sanctum.expiration') ?? 1440);

        $token = $user->createToken('api', ['*'], $expiresAt);

        return ApiResponse::success(
            data: [
                'user' => new UserResource($user->load('roles')),
                'token' => $token->plainTextToken,
                'expires_at' => $expiresAt,
            ],
            message: 'Logged in successfully.',
        );
    }
}
