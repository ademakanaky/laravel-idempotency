<?php

namespace Ademakanaky\EnterpriseIdempotency\Drivers;

use Ademakanaky\EnterpriseIdempotency\Contracts\IdempotencyDriver;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DatabaseDriver implements IdempotencyDriver
{
    public function handle(Request $request, string $key, Closure $next): Response|JsonResponse
    {
        $modelClass = config('idempotency.idempotency_model');
        $userIdentifier = (string) optional($request->user())->getAuthIdentifier() ?: $request->ip();

        $route = $request->route()?->getName() ?? $request->path();
        $hash = $this->hash($request);

        $record = $modelClass::where([
            'idempotency_key' => $key,
            'route' => $route,
            'method' => $request->method(),
            'user_identifier' => $userIdentifier,
        ])->first();

        if ($record) {
            if ($record->request_hash !== $hash) {
                abort(409, 'Idempotency key conflict.');
            }

            if ($record->completed_at) {
                $record->increment('replay_count');

                $response = new Response(
                    $record->response_body,
                    $record->status_code
                );

                foreach ($record->response_headers ?? [] as $key => $values) {
                    foreach ((array)$values as $value) {
                        $response->headers->set($key, $value);
                    }
                }

                $response->headers->set('Idempotency-Replayed', 'true');

                return $response;
            }

            abort(409, 'Request still processing.');
        }

        // Create new record
        $record = $modelClass::create([
            'idempotency_key' => $key,
            'route' => $route,
            'method' => $request->method(),
            'request_hash' => $hash,
            'user_identifier' => $userIdentifier,
            'ip_address' => $request->ip(),
        ]);

        $response = $next($request);

        if (in_array($response->getStatusCode(), config('idempotency.status_codes'), true)) {
            $record->update([
                'status_code' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'response_headers' => $response->headers->all(),
                'completed_at' => now(),
            ]);
        }

        return $response;
    }

    protected function hash(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->method(),
            $request->path(),
            $request->getContent(),
        ]));
    }
}