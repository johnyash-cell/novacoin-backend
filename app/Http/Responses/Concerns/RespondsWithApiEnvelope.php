<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\JsonResponse;

trait RespondsWithApiEnvelope
{
    /**
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>|null  $meta
     */
    protected function successResponse(
        string $message,
        ?array $data = null,
        int $statusCode = 200,
        ?array $meta = null,
    ): JsonResponse {
        $payload = [
            'status' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $data
     */
    protected function errorResponse(
        string $message,
        int $statusCode = 400,
        ?array $errors = null,
        ?array $data = null,
    ): JsonResponse {
        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ], $statusCode);
    }
}
