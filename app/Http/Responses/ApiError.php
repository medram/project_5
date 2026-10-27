<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

final class ApiError
{
    /** @param array<string, array<int, string>>|null $details */
    public static function response(string $code, string $message, int $status, ?array $details = null): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status);
    }
}
