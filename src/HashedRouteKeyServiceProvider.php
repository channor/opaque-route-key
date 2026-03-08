<?php

declare(strict_types=1);

namespace Channor\HashedRouteKey;

use Channor\HashedRouteKey\Console\Commands\GenerateRouteKeyTestCommand;
use Illuminate\Support\ServiceProvider;

class HashedRouteKeyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hashed-route-key.php', 'hashed-route-key');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateRouteKeyTestCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/hashed-route-key.php' => config_path('hashed-route-key.php'),
            ], 'hashed-route-key-config');
        }
    }
}
