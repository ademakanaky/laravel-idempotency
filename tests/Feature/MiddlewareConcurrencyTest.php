<?php

namespace Ademakanaky\EnterpriseIdempotency\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use RuntimeException;
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

    public function test_two_actual_http_requests_with_same_key_are_handled_concurrently(): void
    {
        $port = random_int(18080, 18999);
        $server = $this->startWorkbenchServer($port);

        try {
            $statuses = $this->dispatchConcurrentRequests($port, 'CONCURHTTP123');

            sort($statuses);

            $this->assertSame([201, 409], $statuses);
        } finally {
            $this->stopWorkbenchServer($server);
        }
    }

    protected function dispatchConcurrentRequests(int $port, string $key): array
    {
        $url = sprintf('http://127.0.0.1:%d/idempotency/concurrency', $port);
        $payload = json_encode(['reference' => 'live-race']);

        if ($payload === false) {
            throw new RuntimeException('Failed to encode concurrency payload.');
        }

        $multiHandle = curl_multi_init();
        $handles = [];

        for ($index = 0; $index < 2; $index++) {
            $handle = curl_init($url);

            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Idempotency-Key: '.$key,
                ],
                CURLOPT_POSTFIELDS => $payload,
            ]);

            curl_multi_add_handle($multiHandle, $handle);
            $handles[] = $handle;
        }

        do {
            $status = curl_multi_exec($multiHandle, $running);

            if ($running > 0) {
                curl_multi_select($multiHandle, 1.0);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $statuses = [];

        foreach ($handles as $handle) {
            $this->assertSame('', curl_error($handle));
            $statuses[] = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);

        return $statuses;
    }

    protected function startWorkbenchServer(int $port): array
    {
        $output = [];
        $exitCode = 0;

        exec(
            sprintf(
                'cd %s && php vendor/bin/testbench migrate:fresh --force 2>&1',
                escapeshellarg(dirname(__DIR__, 2))
            ),
            $output,
            $exitCode
        );

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to prepare the workbench database: '.implode("\n", $output));
        }

        $command = sprintf(
            'PHP_CLI_SERVER_WORKERS=4 php vendor/bin/testbench serve --host=127.0.0.1 --port=%d --no-reload',
            $port
        );

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__, 2));

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start the Testbench server.');
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $started = false;
        $output = '';
        $deadline = microtime(true) + 20;

        while (microtime(true) < $deadline) {
            $output .= stream_get_contents($pipes[1]) ?: '';
            $output .= stream_get_contents($pipes[2]) ?: '';

            if ($this->canReachServer($port)) {
                $started = true;
                break;
            }

            usleep(100000);
        }

        if (! $started) {
            $this->stopWorkbenchServer([$process, $pipes]);

            throw new RuntimeException('Testbench server did not start in time. Output: '.$output);
        }

        return [$process, $pipes];
    }

    protected function canReachServer(int $port): bool
    {
        $headers = @get_headers(sprintf('http://127.0.0.1:%d/', $port));

        return is_array($headers) && $headers !== [];
    }

    protected function stopWorkbenchServer(array $server): void
    {
        [$process, $pipes] = $server;

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }
}
