<?php

namespace Ademakanaky\EnterpriseIdempotency\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Ademakanaky\EnterpriseIdempotency\Tests\TestCase;

class MiddlewareConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_fails_when_lock_exists()
    {
        Route::post('/test', fn () => response()->json(['ok' => true]))
            ->middleware('idempotency');

        $key = 'CONCUR123456';

        // Simulate an existing lock i.e. another request holding the lock
        $lock = Cache::lock('idempotency:'.$key, 30);
        $lock->get();

        $response = $this->withHeaders(['Idempotency-Key' => $key])->postJson('/test');

        $response->assertStatus(409);
    }

    public function test_request_succeeds_when_no_lock()
    {
        Route::post('/test', fn () => response()->json(['ok' => true]))
            ->middleware('idempotency');

        $key = 'CONCUR123456';

        $response = $this->withHeaders(['Idempotency-Key' => $key])->postJson('/test');

        $response->assertStatus(200)->assertJson(['ok' => true]);
    }

    //    public function test_http_pool_concurrent_requests()
//    {
//        $key = 'CONCURPOOL123';
//
//        $responses = Http::pool(fn ($pool) => [
//            $pool->as('req1')->post('http://127.0.0.1:8001/test', [], [
//                'Idempotency-Key' => $key
//            ]),
//            $pool->as('req2')->post('http://127.0.0.1:8001/test', [], [
//                'Idempotency-Key' => $key
//            ]),
//        ]);
//
//        $status1 = $responses['req1']->status();
//        $status2 = $responses['req2']->status();
//
//        $this->assertContains(200, [$status1, $status2]);
//        $this->assertContains(409, [$status1, $status2]);
//    }
}
