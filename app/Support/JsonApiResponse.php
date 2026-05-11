<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    public static function paginated(LengthAwarePaginator $paginator, string $message = 'OK'): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'message' => $message,
        ]);
    }

    public static function unauthorized(string $message = 'Unauthenticated.'): JsonResponse
    {
        return response()->json([
            'data' => null,
            'message' => $message,
        ], 401);
    }

    /**
     * Notifications index: paginated list with top-level unread_count (matches SPA expectations).
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  list<array<string, mixed>>  $serializedItems
     */
    public static function notificationsIndex(
        LengthAwarePaginator $paginator,
        array $serializedItems,
        int $unreadCount,
        string $message = 'OK',
    ): JsonResponse {
        return response()->json([
            'data' => $serializedItems,
            'unread_count' => $unreadCount,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'message' => $message,
        ]);
    }
}
