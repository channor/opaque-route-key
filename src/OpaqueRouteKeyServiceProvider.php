<?php

declare(strict_types=1);

namespace Channor\OpaqueRouteKey;

use Channor\OpaqueRouteKey\Console\Commands\GenerateRouteKeyTestCommand;
use Illuminate\Support\ServiceProvider;

class OpaqueRouteKeyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/opaque-route-key.php', 'opaque-route-key');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateRouteKeyTestCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/opaque-route-key.php' => config_path('opaque-route-key.php'),
            ], 'opaque-route-key-config');
        }
    }
}
