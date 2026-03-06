<?php

namespace Ademakanaky\EnterpriseIdempotency\Tests\Unit;

use Ademakanaky\EnterpriseIdempotency\Drivers\HybridDriver;
use Ademakanaky\EnterpriseIdempotency\Models\IdempotencyRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Ademakanaky\EnterpriseIdempotency\Tests\TestCase;

class HybridDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('idempotency.lock.enabled', true);
        config()->set('idempotency.lock.seconds', 10);
        config()->set('idempotency.idempotency_model', IdempotencyRecord::class);
        config()->set('idempotency.status_codes', [200, 201]);
    }

    public function test_first_request_creates_record_and_releases_lock()
    {
        $driver = new HybridDriver();

        $request = Request::create('/test', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $key = 'KEY123';

        $response = $driver->handle($request, $key, fn() => new Response('OK', 200));

        $this->assertDatabaseHas('idempotency_records', [
            'idempotency_key' => $key,
            'route' => $request->path(),
            'method' => 'POST',
            'status_code' => 200,
        ]);

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_second_request_with_same_key_replays_response()
    {
        $driver = new HybridDriver();

        $request = Request::create('/test', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $key = 'KEY456';

        // First request
        $driver->handle($request, $key, fn() => new Response('OK', 200));

        // Second request
        $response = $driver->handle($request, $key, fn() => new Response('SHOULD_NOT_RUN', 200));

        $this->assertEquals('OK', $response->getContent());
        $this->assertEquals('true', $response->headers->get('Idempotency-Replayed'));
    }

    public function test_conflict_when_request_hash_differs()
    {
        $driver = new HybridDriver();
        $key = 'KEY789';

        // First request with some content
        $driver->handle(Request::create('/test', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1'], 'first'), $key, fn() => new Response('OK', 200));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Idempotency key conflict.');

        // Second request with different content
        $driver->handle(Request::create('/test', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1'], 'second'), $key, fn() => new Response('SHOULD_NOT_RUN', 200));
    }
}