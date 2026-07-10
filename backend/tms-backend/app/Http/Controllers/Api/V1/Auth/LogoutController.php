<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();
        $token->delete();

        // Sanctum token deletion doesn't fire Laravel's Logout event on its
        // own (that's a session-guard concept) — dispatch it manually so
        // LogSuccessfulLogout still records the audit entry.
        event(new Logout('sanctum', $user));

        return ApiResponse::success(message: 'Logged out successfully.');
    }
}
