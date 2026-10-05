<?php

namespace Ademakanaky\EnterpriseIdempotency\Tests\Feature;

use Ademakanaky\EnterpriseIdempotency\Tests\TestCase;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class MiddlewareUserScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_idempotency_key_is_scoped_per_authenticated_user(): void
    {
        Route::post('/scoped-test', function (Request $request) {
            return response()->json([
                'ok' => true,
                'user_id' => $request->user()?->getAuthIdentifier(),
            ]);
        })->middleware('idempotency');

        $key = 'USERSCOPED12';

        $this->actingAs($this->makeUser(1))
            ->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/scoped-test', ['amount' => 100])
            ->assertStatus(200)
            ->assertJson(['user_id' => 1]);

        $this->actingAs($this->makeUser(2))
            ->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/scoped-test', ['amount' => 200])
            ->assertStatus(200)
            ->assertJson(['user_id' => 2]);

        $this->assertDatabaseHas('idempotency_records', [
            'idempotency_key' => $key,
            'user_identifier' => '1',
        ]);

        $this->assertDatabaseHas('idempotency_records', [
            'idempotency_key' => $key,
            'user_identifier' => '2',
        ]);

        $this->assertDatabaseCount('idempotency_records', 2);
    }

    protected function makeUser(int $id): Authenticatable
    {
        return new class($id) extends Authenticatable
        {
            public function __construct(private readonly int $userId)
            {
                $this->setAttribute($this->getAuthIdentifierName(), $userId);
            }

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }
        };
    }
}
