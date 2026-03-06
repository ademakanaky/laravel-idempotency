<?php

namespace Ademakanaky\EnterpriseIdempotency\Tests\Unit;

use Ademakanaky\EnterpriseIdempotency\Drivers\DatabaseDriver;
use Ademakanaky\EnterpriseIdempotency\Models\IdempotencyRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Ademakanaky\EnterpriseIdempotency\Tests\TestCase;

class DatabaseDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure the model and accepted status codes
        config()->set('idempotency.idempotency_model', IdempotencyRecord::class);
        config()->set('idempotency.status_codes', [200, 201]);
    }

    public function test_first_request_creates_record_and_saves_response()
    {
        $driver = new DatabaseDriver();
        $request = Request::create('/test', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $key = 'DB123';

        $response = $driver->handle($request, $key, fn() => new Response('OK', 200));

        $this->assertEquals('OK', $response->getContent());

        $this->assertDatabaseHas('idempotency_records', [
            'idempotency_key' => $key,
            'route' => $request->path(),
            'method' => 'POST',
            'status_code' => 200,
        ]);
    }

    public function test_second_request_with_same_key_replays_response()
    {
        $driver = new DatabaseDriver();
        $request = Request::create('/test', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $key = 'DB456';

        // First request caches response in database
        $driver->handle($request, $key, fn() => new Response('OK', 200));

        // Second request should replay
        $response = $driver->handle($request, $key, fn() => new Response('SHOULD_NOT_RUN', 200));

        $this->assertEquals('OK', $response->getContent());
        $this->assertEquals('true', $response->headers->get('Idempotency-Replayed'));
    }

    public function test_conflict_when_request_hash_differs()
    {
        $driver = new DatabaseDriver();
        $key = 'DB789';

        // First request with content "payloadA"
        $driver->handle(Request::create('/test', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1'], 'payloadA'), $key, fn() => new Response('OK', 200));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Idempotency key conflict.');

        // Second request with different payload
        $driver->handle(Request::create('/test', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1'], 'payloadB'), $key, fn() => new Response('SHOULD_NOT_RUN', 200));
    }

    public function test_request_still_processing_aborts()
    {
        $driver = new DatabaseDriver();
        $key = 'DBLOCK';

        $request = Request::create('/test', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        // Create record manually without completed_at
        IdempotencyRecord::create([
            'idempotency_key' => $key,
            'route' => $request->path(),
            'method' => $request->method(),
            'request_hash' => hash('sha256', implode('|', [$request->method(), $request->path(), ''])),
            'user_identifier' => $request->ip(),
            'ip_address' => $request->ip(),
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Request still processing.');

        $driver->handle($request, $key, fn() => new Response('SHOULD_NOT_RUN', 200));
    }
}