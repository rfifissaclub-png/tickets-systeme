<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Audit\AuditService;
use App\Services\Auth\TenantAwareUserResolver;
use App\Services\Tenancy\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    public function __invoke(
        ResetPasswordRequest $request,
        TenantAwareUserResolver $resolver,
        TenantContext $tenantContext,
        AuditService $auditService,
    ): JsonResponse {
        $data = $request->validated();

        $status = $tenantContext->bypass('password reset', function () use ($data, $resolver, $auditService) {
            $tenantId = $resolver->resolveTenantId($data['tenant'] ?? null);

            if ($tenantId === false) {
                return Password::INVALID_USER;
            }

            return Password::reset(
                [
                    'email' => $data['email'],
                    'tenant_id' => $tenantId,
                    'token' => $data['token'],
                    'password' => $data['password'],
                ],
                function ($user) use ($data, $auditService) {
                    $user->forceFill(['password' => $data['password']])->save();
                    // Pre-auth by definition (that's the entire point of a
                    // password reset) — no Sanctum principal is resolved
                    // for this request, so AuditService's default Auth::id()
                    // would silently record no actor at all.
                    $auditService->log('auth.password_reset', $user, actorId: $user->getKey());
                    event(new PasswordResetEvent($user));
                },
            );
        });

        if ($status !== Password::PASSWORD_RESET) {
            // $status is itself a translation key (Password::INVALID_USER ===
            // 'passwords.user', etc.) — see lang/{en,fr}/passwords.php.
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return ApiResponse::success(message: __('messages.auth.password_reset_success'));
    }
}
