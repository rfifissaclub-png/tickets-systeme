<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\Auth\TenantAwareUserResolver;
use App\Services\Tenancy\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function __invoke(
        ForgotPasswordRequest $request,
        TenantAwareUserResolver $resolver,
        TenantContext $tenantContext,
    ): JsonResponse {
        $data = $request->validated();

        $tenantContext->bypass('password reset link request', function () use ($data, $resolver) {
            $tenantId = $resolver->resolveTenantId($data['tenant'] ?? null);

            if ($tenantId === false) {
                return;
            }

            Password::sendResetLink([
                'email' => $data['email'],
                'tenant_id' => $tenantId,
            ]);
        });

        // Always the same response whether or not the email/tenant matched
        // anything, so this endpoint can't be used to enumerate accounts.
        return ApiResponse::success(message: __('messages.auth.password_reset_link_sent'));
    }
}
