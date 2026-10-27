<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiError;
use App\Models\ApiIdempotencyKey;
use App\Models\Workspace;
use App\Models\WorkspaceApiToken;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HandleApiIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if (! $request->isMethod('POST') || $key === null) {
            return $next($request);
        }

        if (! is_string($key) || trim($key) === '' || strlen($key) > 255 || preg_match('/[\r\n]/', $key) === 1) {
            return ApiError::response('invalid_idempotency_key', 'The Idempotency-Key header is invalid.', 422);
        }

        $workspace = $request->attributes->get('api_workspace');
        $token = $request->user()?->currentAccessToken();
        abort_unless($workspace instanceof Workspace && $token instanceof WorkspaceApiToken, 401);

        $requestHash = hash('sha256', implode("\n", [
            (string) $request->route()?->getName(),
            $request->method(),
            (string) $request->getQueryString(),
            $request->getContent(),
        ]));
        $attributes = [
            'workspace_id' => $workspace->getKey(),
            'token_id' => $token->getKey(),
            'idempotency_key' => $key,
        ];

        try {
            return DB::transaction(function () use ($request, $next, $attributes, $requestHash): Response {
                $record = ApiIdempotencyKey::query()
                    ->where($attributes)
                    ->lockForUpdate()
                    ->first();

                if ($record?->expires_at->isPast()) {
                    $record->delete();
                    $record = null;
                }

                if ($record !== null) {
                    return $this->existingResponse($record, $requestHash);
                }

                $record = ApiIdempotencyKey::query()->create([
                    ...$attributes,
                    'request_hash' => $requestHash,
                    'expires_at' => now()->addDay(),
                ]);

                try {
                    $response = $next($request);
                } catch (Throwable $exception) {
                    $record->delete();
                    throw $exception;
                }

                if (! $response instanceof JsonResponse || $response->getStatusCode() >= 500) {
                    $record->delete();

                    return $response;
                }

                $record->update([
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $response->getData(true),
                ]);

                return $response;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $record = ApiIdempotencyKey::query()->where($attributes)->first();
            if ($record === null) {
                throw $exception;
            }

            return $this->existingResponse($record, $requestHash);
        }
    }

    private function existingResponse(ApiIdempotencyKey $record, string $requestHash): Response
    {
        if (! hash_equals($record->request_hash, $requestHash)) {
            return ApiError::response(
                'idempotency_conflict',
                'This idempotency key was already used with a different request.',
                409,
            );
        }

        if ($record->response_status === null || $record->response_body === null) {
            return ApiError::response(
                'idempotency_in_progress',
                'A request with this idempotency key is still processing.',
                409,
            );
        }

        return response()->json($record->response_body, $record->response_status);
    }
}
