<?php

namespace Ademakanaky\EnterpriseIdempotency\Tests\Unit;

use Ademakanaky\EnterpriseIdempotency\Drivers\CacheDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Ademakanaky\EnterpriseIdempotency\Tests\TestCase;

class CacheDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config()->set('idempotency.status_codes', [200, 201]);
        config()->set('idempotency.ttl_minutes', 60);
    }

    public function test_first_request_caches_response()
    {
        $driver = new CacheDriver();

        $request = Request::create('/test', 'POST', [], [], [], [], 'payload1');
        $key = 'CACHE123';

        $response = $driver->handle($request, $key, fn() => new Response('OK', 200));

        $this->assertEquals('OK', $response->getContent());

        $cached = Cache::get('idempotency:'.$key);

        $this->assertIsArray($cached);
        $this->assertEquals(200, $cached['status']);
        $this->assertEquals('OK', $cached['body']);
        $this->assertArrayHasKey('headers', $cached);
    }

    public function test_second_request_replays_cached_response()
    {
        $driver = new CacheDriver();

        $request = Request::create('/test', 'POST', [], [], [], [], 'payload2');
        $key = 'CACHE456';

        // First request caches it
        $driver->handle($request, $key, fn() => new Response('OK', 200));

        // Second request
        $response = $driver->handle($request, $key, fn() => new Response('SHOULD_NOT_RUN', 200));

        $this->assertEquals('OK', $response->getContent());
        $this->assertEquals('true', $response->headers->get('Idempotency-Replayed'));
    }

    public function test_conflict_when_request_hash_differs()
    {
        $driver = new CacheDriver();

        $key = 'CACHE789';

        // First request with content "payloadA"
        $driver->handle(Request::create('/test', 'POST', [], [], [], [], 'payloadA'), $key, fn() => new Response('OK', 200));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Idempotency key conflict.');

        // Second request with content "payloadB" → should fail
        $driver->handle(Request::create('/test', 'POST', [], [], [], [], 'payloadB'), $key, fn() => new Response('SHOULD_NOT_RUN', 200));
    }
}