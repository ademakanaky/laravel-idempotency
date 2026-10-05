<?php

namespace Ademakanaky\EnterpriseIdempotency\Concerns;

use Illuminate\Http\Request;

trait InteractsWithIdempotencyRequests
{
    protected function resolveUser(Request $request): string
    {
        $resolver = config('idempotency.user_resolver');

        if (is_callable($resolver)) {
            return (string) $resolver($request);
        }

        return (string) optional($request->user())->getAuthIdentifier() ?: $request->ip();
    }

    protected function hash(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->method(),
            $request->path(),
            $this->canonicalizePayload($request->getContent()),
        ]));
    }

    protected function canonicalizePayload(string $payload): string
    {
        if ($payload === '') {
            return $payload;
        }

        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $payload;
        }

        return json_encode(
            $this->sortJsonValue($decoded),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: $payload;
    }

    protected function sortJsonValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item) => $this->sortJsonValue($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortJsonValue($item);
        }

        return $value;
    }
}
