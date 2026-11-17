<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\IdempotencyStatus;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headerName = config('payment.idempotency.header', 'Idempotency-Key');
        $idempotencyKey = $request->header($headerName);

        // If no idempotency key is supplied on a mutating request, enforce it
        if (! $idempotencyKey) {
            return new JsonResponse([
                'error' => 'Missing required Idempotency-Key header.',
                'code' => 'MISSING_IDEMPOTENCY_KEY',
            ], Response::HTTP_BAD_REQUEST);
        }

        $normalizedParams = $this->normalizeParams($request->all());
        $paramsHash = hash('sha256', json_encode($normalizedParams));
        $path = $request->path();

        // Check if key already exists
        /** @var IdempotencyKey|null $existingKey */
        $existingKey = IdempotencyKey::where('key', $idempotencyKey)->first();

        if ($existingKey) {
            // 1. Detect payload tampering / conflict
            if ($existingKey->request_params_hash !== $paramsHash) {
                return new JsonResponse([
                    'error' => 'Idempotency key was previously used with different request parameters.',
                    'code' => 'IDEMPOTENCY_PAYLOAD_MISMATCH',
                ], Response::HTTP_CONFLICT);
            }

            // 2. Detect concurrent in-flight request
            if ($existingKey->isProcessing()) {
                if ($existingKey->locked_at && $existingKey->locked_at->diffInSeconds(now()) < 60) {
                    return new JsonResponse([
                        'error' => 'A request with this Idempotency-Key is currently being processed.',
                        'code' => 'CONCURRENT_REQUEST_IN_PROGRESS',
                    ], Response::HTTP_CONFLICT);
                }
            }

            // 3. Return cached response for completed requests (Zero Double-Charges)
            if ($existingKey->isCompleted()) {
                return new JsonResponse(
                    $existingKey->response_body,
                    $existingKey->response_code ?? Response::HTTP_OK,
                    [
                        'Idempotent-Replay' => 'true',
                        'X-Cache-Lookup' => 'HIT',
                    ]
                );
            }
        }

        // Lock new idempotency key
        $keyRecord = DB::transaction(function () use ($idempotencyKey, $path, $paramsHash, $existingKey) {
            if ($existingKey) {
                $existingKey->update([
                    'status' => IdempotencyStatus::Processing,
                    'locked_at' => now(),
                ]);

                return $existingKey;
            }

            return IdempotencyKey::create([
                'key' => $idempotencyKey,
                'request_path' => $path,
                'request_params_hash' => $paramsHash,
                'status' => IdempotencyStatus::Processing,
                'locked_at' => now(),
                'expires_at' => now()->addHours((int) config('payment.idempotency.ttl_hours', 24)),
            ]);
        });

        try {
            $response = $next($request);

            if ($response->isSuccessful()) {
                $responseBody = json_decode($response->getContent() ?: '{}', true) ?: [];

                $keyRecord->update([
                    'status' => IdempotencyStatus::Completed,
                    'response_code' => $response->getStatusCode(),
                    'response_body' => $responseBody,
                ]);
            } else {
                $keyRecord->update([
                    'status' => IdempotencyStatus::Failed,
                    'response_code' => $response->getStatusCode(),
                ]);
            }

            return $response;
        } catch (Throwable $e) {
            $keyRecord->update([
                'status' => IdempotencyStatus::Failed,
            ]);

            throw $e;
        }
    }

    /**
     * Recursively sort parameters for consistent fingerprinting.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function normalizeParams(array $params): array
    {
        ksort($params);

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = $this->normalizeParams($value);
            }
        }

        return $params;
    }
}
