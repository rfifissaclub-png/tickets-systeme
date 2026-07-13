<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Tenancy\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    /**
     * Handles the signed link from the verification email. Unauthenticated
     * by nature — a mail client following this link has no Sanctum bearer
     * token to attach — so the signed URL (checked by the `signed`
     * middleware on this route) plus the hash check below are the entire
     * authorization boundary, not a Sanctum guard.
     */
    public function __invoke(Request $request, TenantContext $tenantContext, AuditService $auditService): JsonResponse
    {
        $user = $tenantContext->bypass('email verification via signed link', function () use ($request, $auditService) {
            $user = User::query()->find($request->route('id'));

            if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
                return null;
            }

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
                // Unauthenticated by nature (see this controller's own
                // docblock — a signed link, not a Sanctum guard) — no
                // principal is resolved for this request, so AuditService's
                // default Auth::id() would silently record no actor at all.
                $auditService->log('auth.email_verified', $user, actorId: $user->getKey());
            }

            return $user;
        });

        if ($user === null) {
            return ApiResponse::error(__('messages.auth.invalid_verification_link'), 403, code: 'invalid_verification_link');
        }

        return ApiResponse::success(message: __('messages.auth.email_verified_success'));
    }
}
