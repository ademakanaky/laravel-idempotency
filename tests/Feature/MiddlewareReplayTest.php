<?php

namespace Ademakanaky\EnterpriseIdempotency\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Ademakanaky\EnterpriseIdempotency\Tests\TestCase;

class MiddlewareReplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_replay_returns_same_response()
    {
        Route::post('/test', fn() => response()->json(['ok' => true]))
            ->middleware('idempotency');

        $key = 'ABC12345678';

        $response1 = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/test');

        $response1->assertStatus(200)->assertJson(['ok' => true]);
        $this->assertFalse($response1->headers->has('Idempotency-Replayed'));

        $response2 = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/test');

        $response2->assertStatus(200)
            ->assertJson(['ok' => true])
            ->assertHeader('Idempotency-Replayed', 'true');
    }
}