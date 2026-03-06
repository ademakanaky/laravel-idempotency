<?php

namespace Ademakanaky\EnterpriseIdempotency\Http\Middleware;

use Ademakanaky\EnterpriseIdempotency\Services\IdempotencyManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IdempotencyMiddleware
{
    public function __construct(protected IdempotencyManager $manager) {}

    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        if (! config('idempotency.enabled')) {
            return $next($request);
        }

        if (! in_array($request->method(), config('idempotency.apply_to_methods'), true)) {
            return $next($request);
        }

        $header = config('idempotency.header');
        $key = $request->header($header);

        if (! $key) {
            return $next($request);
        }

        return $this->manager->handle($request, $key, $next);
    }
}