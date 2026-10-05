<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $databasePath = __DIR__.'/../../../database/workbench.sqlite';
        $cachePath = __DIR__.'/../../../storage/framework/cache/data';

        if (! file_exists($databasePath)) {
            touch($databasePath);
        }

        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
        ]);

        config()->set('cache.default', 'file');
        config()->set('cache.stores.file', [
            'driver' => 'file',
            'path' => $cachePath,
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        config()->set('idempotency.driver', 'hybrid');
        config()->set('idempotency.lock.enabled', true);
        config()->set('idempotency.lock.seconds', 5);
    }
}
