<?php

namespace Ademakanaky\EnterpriseIdempotency\Tests\Feature;

use Ademakanaky\EnterpriseIdempotency\Models\IdempotencyRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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

    public function test_replay_returns_stored_201_headers_and_body(): void
    {
        Route::post('/orders', function (Request $request) {
            return response()
                ->json([
                    'created' => true,
                    'reference' => $request->input('reference'),
                ], 201)
                ->header('Location', '/orders/123')
                ->header('X-Order-Version', 'v1');
        })->middleware('idempotency');

        $key = 'ORDER2011234';
        $payload = ['reference' => 'order-123'];

        $response1 = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/orders', $payload);

        $response1->assertStatus(201)
            ->assertExactJson([
                'created' => true,
                'reference' => 'order-123',
            ])
            ->assertHeader('Location', '/orders/123')
            ->assertHeader('X-Order-Version', 'v1');

        $record = IdempotencyRecord::query()->where('idempotency_key', $key)->firstOrFail();

        $this->assertSame(201, $record->status_code);
        $this->assertSame($response1->getContent(), $record->response_body);
        $this->assertSame(['/orders/123'], $record->response_headers['location'] ?? null);
        $this->assertSame(['v1'], $record->response_headers['x-order-version'] ?? null);

        $response2 = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/orders', $payload);

        $response2->assertStatus(201)
            ->assertExactJson([
                'created' => true,
                'reference' => 'order-123',
            ])
            ->assertHeader('Location', '/orders/123')
            ->assertHeader('X-Order-Version', 'v1')
            ->assertHeader('Idempotency-Replayed', 'true');
    }

    public function test_replay_prevents_duplicate_database_side_effects(): void
    {
        Schema::create('test_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->timestamps();
        });

        Route::post('/orders-with-side-effect', function (Request $request) {
            $orderId = DB::table('test_orders')->insertGetId([
                'reference' => $request->string('reference')->toString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()
                ->json([
                    'id' => $orderId,
                    'reference' => $request->input('reference'),
                ], 201)
                ->header('Location', '/orders/'.$orderId);
        })->middleware('idempotency');

        $key = 'SIDEEFFECT12';
        $payload = ['reference' => 'order-456'];

        $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/orders-with-side-effect', $payload)
            ->assertStatus(201);

        $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/orders-with-side-effect', $payload)
            ->assertStatus(201)
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame(1, DB::table('test_orders')->count());
        $this->assertSame('order-456', DB::table('test_orders')->value('reference'));
    }
}
