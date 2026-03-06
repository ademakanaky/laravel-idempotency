<?php

namespace Ademakanaky\EnterpriseIdempotency\Providers;

use Ademakanaky\EnterpriseIdempotency\Drivers\CacheDriver;
use Ademakanaky\EnterpriseIdempotency\Drivers\DatabaseDriver;
use Ademakanaky\EnterpriseIdempotency\Drivers\HybridDriver;
use Ademakanaky\EnterpriseIdempotency\Http\Middleware\IdempotencyMiddleware;
use Ademakanaky\EnterpriseIdempotency\Services\IdempotencyManager;
use Illuminate\Support\ServiceProvider;

class IdempotencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/idempotency.php',
            'idempotency'
        );

        $driver = match (config('idempotency.driver')) {
            'cache' => new CacheDriver(),
            'database' => new DatabaseDriver(),
            default => new HybridDriver(),
        };

        $this->app->singleton(IdempotencyManager::class, fn() => new IdempotencyManager($driver));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/idempotency.php' =>
                config_path('idempotency.php'),
        ], 'idempotency-config');

        $this->publishes([
            __DIR__ . '/../../database/migrations/' =>
                database_path('migrations'),
        ], 'idempotency-migrations');

        $this->app['router']->aliasMiddleware(
            'idempotency',
            IdempotencyMiddleware::class
        );
    }
}