<?php

namespace Ademakanaky\EnterpriseIdempotency\Contracts;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

interface IdempotencyDriver
{
    public function handle(Request $request, string $key, Closure $next): Response|JsonResponse;
}