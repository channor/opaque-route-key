<?php

declare(strict_types=1);

namespace Channor\OpaqueRouteKey\Tests;

use Channor\OpaqueRouteKey\OpaqueRouteKeyServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $this->assertArrayHasKey(
            OpaqueRouteKeyServiceProvider::class,
            $this->app->getLoadedProviders()
        );
    }

    public function test_config_keys_are_present(): void
    {
        $this->assertArrayHasKey('salt', config('opaque-route-key'));
        $this->assertArrayHasKey('append_route_key', config('opaque-route-key'));
        $this->assertArrayHasKey('default_attribute_name', config('opaque-route-key'));
        $this->assertArrayHasKey('min_payload_length', config('opaque-route-key'));
        $this->assertArrayHasKey('check_length', config('opaque-route-key'));
        $this->assertArrayHasKey('offset_multiplier', config('opaque-route-key'));
        $this->assertArrayHasKey('reserved_words', config('opaque-route-key'));
        $this->assertArrayHasKey('reserved_words_case_sensitive', config('opaque-route-key'));
        $this->assertArrayHasKey('auto_reserve_model_names', config('opaque-route-key'));
        $this->assertArrayHasKey('reserved_word_max_attempts', config('opaque-route-key'));
        $this->assertTrue(config('opaque-route-key.reserved_words_case_sensitive'));
        $this->assertFalse(config('opaque-route-key.auto_reserve_model_names'));
    }

    public function test_config_publishable_under_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(OpaqueRouteKeyServiceProvider::class, 'opaque-route-key-config');

        $this->assertNotEmpty($paths);
        $this->assertContains(config_path('opaque-route-key.php'), $paths);
    }

    public function test_legacy_config_is_not_publishable(): void
    {
        $paths = ServiceProvider::pathsToPublish(OpaqueRouteKeyServiceProvider::class, 'hashed-route-key-config');

        $this->assertEmpty($paths);
    }

    public function test_route_key_generate_test_command_is_registered(): void
    {
        $this->assertArrayHasKey('route-key:generate-test', Artisan::all());
    }
}
