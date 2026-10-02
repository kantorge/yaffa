<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes a write endpoint safe to retry: a repeated request with the same Idempotency-Key
 * replays the stored response instead of running the controller again.
 */
class EnsureIdempotent
{
    /**
     * A request still marked as running after this many minutes is assumed to have crashed.
     */
    private const STALE_AFTER_MINUTES = 10;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        $user = $request->user();

        if ($key === null || $key === '' || $user === null) {
            return $next($request);
        }

        if (mb_strlen($key) > 255) {
            return $this->error('IDEMPOTENCY_KEY_INVALID', 'The Idempotency-Key must not exceed 255 characters.', 422);
        }

        $attributes = [
            'user_id' => $user->id,
            'route' => $request->route()?->getName() ?? $request->path(),
            'key' => $key,
        ];
        $hash = $this->requestHash($request);

        try {
            $record = IdempotencyKey::query()->create($attributes + ['request_hash' => $hash]);
        } catch (UniqueConstraintViolationException) {
            return $this->handleExisting($request, $next, $attributes, $hash);
        }

        return $this->run($request, $next, $record);
    }

    /**
     * @param array{user_id: int, route: string, key: string} $attributes
     */
    private function handleExisting(Request $request, Closure $next, array $attributes, string $hash): Response
    {
        $existing = IdempotencyKey::query()->where($attributes)->first();

        if ($existing === null) {
            // Pruned between the insert and the lookup; treat as a fresh request.
            return $next($request);
        }

        if ($existing->request_hash !== $hash) {
            return $this->error('IDEMPOTENCY_KEY_REUSED', 'This Idempotency-Key was already used with a different request.', 422);
        }

        if ($existing->status_code === null) {
            if ($existing->created_at?->lt(now()->subMinutes(self::STALE_AFTER_MINUTES))) {
                // The original attempt died mid-flight; release the key and let this retry run.
                $existing->delete();

                return $this->run($request, $next, IdempotencyKey::query()->create($attributes + ['request_hash' => $hash]));
            }

            return $this->error('IDEMPOTENCY_REQUEST_IN_PROGRESS', 'The original request is still being processed.', 409);
        }

        return response()
            ->json(json_decode((string) $existing->response_body, true), $existing->status_code)
            ->header('Idempotent-Replayed', 'true');
    }

    private function run(Request $request, Closure $next, IdempotencyKey $record): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $record->delete();

            throw $e;
        }

        if ($response instanceof JsonResponse && $response->isSuccessful()) {
            $record->update([
                'status_code' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ]);
        } else {
            // Failures are not remembered, so the client can fix the request and retry with the same key.
            $record->delete();
        }

        return $response;
    }

    /**
     * Uploaded files are hashed by content, since multipart boundaries differ between retries.
     */
    private function requestHash(Request $request): string
    {
        $files = [];
        $uploads = $request->allFiles();
        array_walk_recursive($uploads, function ($file) use (&$files) {
            $files[] = [$file->getClientOriginalName(), $file->getSize(), sha1_file($file->getRealPath())];
        });

        return hash('sha256', json_encode([$request->post(), $request->query(), $files]));
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
