<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Every API response — success or error — shares this envelope shape so
 * frontend clients never need to branch on which endpoint they called.
 */
class ApiResponse
{
    public static function success(mixed $data = null, ?string $message = null, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta === [] ? null : $meta,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function error(string $message, int $status = 400, array $errors = [], ?string $code = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors === [] ? null : $errors,
            'code' => $code,
        ], $status);
    }
}
