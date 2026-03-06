<?php

namespace Ademakanaky\EnterpriseIdempotency\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Ademakanaky\EnterpriseIdempotency\Tests\TestCase;

class MiddlewareConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_conflict_on_different_payload_same_key()
    {
        Route::post('/test', fn() => response()->json(['ok' => true]))
            ->middleware('idempotency');

        $key = 'XYZ98765432';

        $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/test', ['foo' => 'bar'])
            ->assertStatus(200);

        $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/test', ['foo' => 'baz'])
            ->assertStatus(409); // Conflict
    }
}