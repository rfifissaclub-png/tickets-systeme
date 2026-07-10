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
    private const STATUS_MESSAGES = [
        Password::INVALID_USER => 'Unable to reset the password for the given email.',
        Password::INVALID_TOKEN => 'This password reset token is invalid or has expired.',
        Password::RESET_THROTTLED => 'Please wait before retrying.',
    ];

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
                    $auditService->log('auth.password_reset', $user);
                    event(new PasswordResetEvent($user));
                },
            );
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [self::STATUS_MESSAGES[$status] ?? 'Unable to reset the password.'],
            ]);
        }

        return ApiResponse::success(message: 'Password reset successfully.');
    }
}
