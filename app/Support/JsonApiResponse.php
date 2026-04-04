<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Consistent JSON envelope for API responses: { "data", "message" }.
 */
final class JsonApiResponse
{
    public static function success(mixed $data, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    public static function unauthorized(string $message = 'Unauthenticated.'): JsonResponse
    {
        return response()->json([
            'data' => null,
            'message' => $message,
        ], 401);
    }
}
