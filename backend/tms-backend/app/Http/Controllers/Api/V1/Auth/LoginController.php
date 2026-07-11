<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\TenantAwareUserResolver;
use App\Services\Tenancy\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
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
                'email' => [__('messages.auth.credentials_invalid')],
            ]);
        }

        // This request isn't behind the tenant.context middleware (login is
        // necessarily public), so binding happens here directly — including
        // Spatie's team resolver for a Super Admin (no real tenant), or
        // user->load('roles') below would look up their role assignment
        // under the wrong team and come back empty. See
        // TenantContext::PLATFORM_TEAM_ID.
        if ($user->tenant_id !== null) {
            $tenantContext->set($user->tenant_id);
        } else {
            $tenantContext->bindPlatformTeam();
        }

        // Deliberately not Auth::login($user): that establishes a real
        // session on the default ('web') guard, which this pure
        // Bearer-token API never intends to use — a client never sends the
        // resulting cookie back, so it's normally just inert, but within a
        // single test process (where Laravel's HTTP testing helpers carry
        // session state across sequential requests) it actively breaks
        // things: a later authenticated request in the same test starts
        // resolving the user via that stray session instead of its own
        // bearer token (Sanctum returns a TransientToken for a
        // session-resolved user, not the real PersonalAccessToken), and a
        // *second* login() call in the same test can find the "default"
        // guard already flipped to Sanctum's RequestGuard, which has no
        // login() method at all. LogSuccessfulLogin only needs the Login
        // event itself, not an actual session — dispatch it directly,
        // mirroring LogoutController's manual Logout event dispatch below.
        event(new Login('sanctum', $user, false));

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
            message: __('messages.auth.login_success'),
        );
    }
}
