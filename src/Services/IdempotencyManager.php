<?php

namespace Ademakanaky\EnterpriseIdempotency\Services;

use Ademakanaky\EnterpriseIdempotency\Contracts\IdempotencyDriver;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IdempotencyManager
{
    public function __construct(protected IdempotencyDriver $driver) {}

    public function handle(Request $request, string $key, Closure $next): Response|JsonResponse
    {
        return $this->driver->handle($request, $key, $next);
    }
}