<?php

declare(strict_types=1);

namespace Channor\HashedRouteKey\Tests;

use Channor\HashedRouteKey\HashedRouteKeyServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $this->assertArrayHasKey(
            HashedRouteKeyServiceProvider::class,
            $this->app->getLoadedProviders()
        );
    }

    public function test_config_keys_are_present(): void
    {
        $this->assertArrayHasKey('salt', config('hashed-route-key'));
        $this->assertArrayHasKey('append_route_key', config('hashed-route-key'));
        $this->assertArrayHasKey('default_attribute_name', config('hashed-route-key'));
        $this->assertArrayHasKey('min_payload_length', config('hashed-route-key'));
        $this->assertArrayHasKey('check_length', config('hashed-route-key'));
        $this->assertArrayHasKey('offset_multiplier', config('hashed-route-key'));
    }

    public function test_config_publishable_under_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(HashedRouteKeyServiceProvider::class, 'hashed-route-key-config');

        $this->assertNotEmpty($paths);
        $this->assertContains(config_path('hashed-route-key.php'), $paths);
    }

    public function test_route_key_generate_test_command_is_registered(): void
    {
        $this->assertArrayHasKey('route-key:generate-test', Artisan::all());
    }
}
