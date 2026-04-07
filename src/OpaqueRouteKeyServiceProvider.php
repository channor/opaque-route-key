<?php

declare(strict_types=1);

namespace Channor\OpaqueRouteKey;

use Channor\OpaqueRouteKey\Console\Commands\GenerateRouteKeyTestCommand;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

class OpaqueRouteKeyServiceProvider extends ServiceProvider
{
    /**
     * @var list<string>
     */
    private const CONFIG_KEYS = [
        'salt',
        'append_route_key',
        'default_attribute_name',
        'min_payload_length',
        'check_length',
        'offset_multiplier',
        'reserved_words',
        'reserved_words_case_sensitive',
        'auto_reserve_model_names',
        'reserved_word_max_attempts',
    ];

    public function register(): void
    {
        $config = $this->config();
        $hadOpaqueConfig = is_array($config->get('opaque-route-key'));
        $hadLegacyConfig = is_array($config->get('hashed-route-key'));

        $this->mergeConfigFrom(__DIR__.'/../config/opaque-route-key.php', 'opaque-route-key');
        $this->mergeConfigFrom(__DIR__.'/../config/hashed-route-key.php', 'hashed-route-key');

        if (! $hadOpaqueConfig && $hadLegacyConfig) {
            $this->copyLegacyConfigToOpaqueConfig();
        }
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

            $this->publishes([
                __DIR__.'/../config/hashed-route-key.php' => config_path('hashed-route-key.php'),
            ], 'hashed-route-key-config');
        }
    }

    private function copyLegacyConfigToOpaqueConfig(): void
    {
        $legacyConfig = $this->config()->get('hashed-route-key', []);

        if (! is_array($legacyConfig)) {
            return;
        }

        foreach (self::CONFIG_KEYS as $key) {
            if (array_key_exists($key, $legacyConfig)) {
                $this->config()->set('opaque-route-key.'.$key, $legacyConfig[$key]);
            }
        }
    }

    private function config(): ConfigRepository
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make('config');

        return $config;
    }
}
