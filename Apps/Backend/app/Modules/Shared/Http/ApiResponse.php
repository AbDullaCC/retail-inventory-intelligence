<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http;

use App\Modules\Shared\DTOs\PaginatedData;
use Illuminate\Http\JsonResponse;

/**
 * Centralises the JSON response envelope so every endpoint is shaped consistently:
 *
 *   single resource : { "data": {...}, "message"?: "..." }
 *   collection      : { "data": [...], "message"?: "..." }
 *   paginated       : { "data": [...], "meta": {...} }
 *   bare message    : { "message": "..." }
 */
final class ApiResponse
{
    public static function item(mixed $data, ?string $message = null, int $status = 200): JsonResponse
    {
        return self::payload(['data' => $data], $message, $status);
    }

    /**
     * @param  array<int, mixed>  $items
     */
    public static function collection(array $items, ?string $message = null, int $status = 200): JsonResponse
    {
        return self::payload(['data' => $items], $message, $status);
    }

    public static function paginated(PaginatedData $paginated, int $status = 200): JsonResponse
    {
        return response()->json($paginated->toArray(), $status);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function payload(array $payload, ?string $message, int $status): JsonResponse
    {
        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }
}
