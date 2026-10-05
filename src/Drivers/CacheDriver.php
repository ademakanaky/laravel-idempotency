<?php

namespace Ademakanaky\EnterpriseIdempotency\Drivers;

use Ademakanaky\EnterpriseIdempotency\Concerns\InteractsWithIdempotencyRequests;
use Ademakanaky\EnterpriseIdempotency\Contracts\IdempotencyDriver;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Response;

class CacheDriver implements IdempotencyDriver
{
    use InteractsWithIdempotencyRequests;

    public function handle(Request $request, string $key, Closure $next): Response|JsonResponse
    {
        $cacheKey = 'idempotency:'.$key;
        $requestHash = $this->hash($request);

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            if (($cached['request_hash'] ?? null) !== $requestHash) {
                abort(409, 'Idempotency key conflict.');
            }

            $response = new Response(
                $cached['body'] ?? '',
                $cached['status'] ?? 200
            );

            foreach ($cached['headers'] ?? [] as $header => $values) {
                foreach ((array)$values as $value) {
                    $response->headers->set($header, $value);
                }
            }

            $response->headers->set('Idempotency-Replayed', 'true');

            return $response;
        }

        $response = $next($request);

        if (in_array($response->getStatusCode(), config('idempotency.status_codes'), true)) {
            $ttl = config('idempotency.ttl_minutes', 60) * 60;

            Cache::put($cacheKey, [
                'request_hash' => $requestHash,
                'status' => $response->getStatusCode(),
                'body' => $response->getContent(),
                'headers' => $response->headers->all(),
            ], $ttl);
        }

        return $response;
    }
}
