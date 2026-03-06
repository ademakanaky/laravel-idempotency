<?php

namespace Ademakanaky\EnterpriseIdempotency\Tests;

use Ademakanaky\EnterpriseIdempotency\Providers\IdempotencyServiceProvider;
use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            IdempotencyServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $databasePath = __DIR__.'/temp.sqlite';

        if (! file_exists($databasePath)) {
            touch($databasePath);
        }

        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
//            'database' => ':memory:',
            'database' => $databasePath,
            'prefix' => '',
        ]);

        // Redis configuration for tests
        $app['config']->set('database.redis', [
            'client' => 'array', // 'phpredis' or 'predis' if using predis package
            'default' => [
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'password' => env('REDIS_PASSWORD', null),
                'port' => env('REDIS_PORT', 6379),
                'database' => 0,
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Load package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Run Laravel migrations (optional)
        $this->artisan('migrate', ['--database' => 'sqlite'])->run();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();

        $databasePath = __DIR__.'/temp.sqlite';
        if (file_exists($databasePath)) {
            unlink($databasePath);
        }
    }
}