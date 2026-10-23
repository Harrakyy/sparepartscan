<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'error' => null], $status);
    }

    public static function fail(string $message, int $status, mixed $data = null): JsonResponse
    {
        return response()->json(['success' => false, 'data' => $data, 'error' => $message], $status);
    }
}
