<?php

namespace Ademakanaky\EnterpriseIdempotency\Drivers;

use Ademakanaky\EnterpriseIdempotency\Concerns\InteractsWithIdempotencyRequests;
use Ademakanaky\EnterpriseIdempotency\Contracts\IdempotencyDriver;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HybridDriver implements IdempotencyDriver
{
    use InteractsWithIdempotencyRequests;

    public function handle(Request $request, string $key, Closure $next): Response|JsonResponse
    {
        $lock = null;

        if (config('idempotency.lock.enabled')) {
            $lock = Cache::lock('idempotency:'.$key, config('idempotency.lock.seconds', 10));

            if (! $lock->get()) {
                abort(409, 'Duplicate request in progress.');
            }
        }

        try {
            return DB::transaction(function () use ($request, $key, $next) {

                $modelClass = config('idempotency.idempotency_model');
                $userIdentifier = $this->resolveUser($request);

                $route = $request->route()?->getName() ?? $request->path();
                $hash = $this->hash($request);

                $record = $modelClass::where([
                    'idempotency_key' => $key,
                    'route' => $route,
                    'method' => $request->method(),
                    'user_identifier' => $userIdentifier,
                ])->lockForUpdate()->first();

                if ($record) {

                    if ($record->request_hash !== $hash) {
                        abort(409, 'Idempotency key conflict.');
                    }

                    if ($record->completed_at) {

                        $record->increment('replay_count');

                        return $this->replay($record);
                    }

                    abort(409, 'Request still processing.');
                }

                $record = $modelClass::create([
                    'idempotency_key' => $key,
                    'route' => $route,
                    'method' => $request->method(),
                    'request_hash' => $hash,
                    'user_identifier' => $userIdentifier,
                    'ip_address' => $request->ip(),
                    'locked_at' => now(),
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
            });
        } finally {
            optional($lock)->release();
        }
    }

    protected function replay($record): Response
    {
        $response = new Response($record->response_body, $record->status_code);

        foreach ($record->response_headers ?? [] as $key => $values) {
            foreach ($values as $value) {
                $response->headers->set($key, $value);
            }
        }

        $response->headers->set('Idempotency-Replayed', 'true');

        return $response;
    }
}
