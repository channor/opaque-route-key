<?php

declare(strict_types=1);

namespace Channor\OpaqueRouteKey\Tests;

use Channor\HashedRouteKey\HashedRouteKeyCodec;
use Channor\HashedRouteKey\HashedRouteKeyServiceProvider;
use Channor\HashedRouteKey\UsesHashedRouteKey;
use Channor\OpaqueRouteKey\OpaqueRouteKeyCodec;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class BackwardCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('legacy_route_key_models', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('legacy_route_key_models');

        parent::tearDown();
    }

    public function test_deprecated_codec_preserves_v1_outputs(): void
    {
        $legacyCodec = new HashedRouteKeyCodec(salt: 'test');
        $opaqueCodec = new OpaqueRouteKeyCodec(salt: 'test');

        $expected = [
            0 => 'lJJwPbu',
            1 => 'lJle2zQ',
            2 => 'lJxFx69',
            42 => 'lJmMMgc',
            500 => 'lYemSSw',
        ];

        foreach ($expected as $id => $routeKey) {
            $this->assertSame($routeKey, $legacyCodec->encode($id));
            $this->assertSame($routeKey, $opaqueCodec->encode($id));
            $this->assertSame($id, $legacyCodec->decode($routeKey));
            $this->assertSame($id, $opaqueCodec->decode($routeKey));
        }
    }

    public function test_deprecated_trait_uses_legacy_config_name(): void
    {
        config([
            'hashed-route-key.salt' => 'legacy-route-key-salt',
            'opaque-route-key.salt' => 'different-opaque-route-key-salt',
        ]);

        $model = LegacyRouteKeyModel::create(['name' => 'test']);
        $routeKey = $model->getRouteKey();

        $legacyCodec = new HashedRouteKeyCodec(salt: 'legacy-route-key-salt:legacy_route_key_model');
        $opaqueCodec = new OpaqueRouteKeyCodec(salt: 'different-opaque-route-key-salt:legacy_route_key_model');

        $this->assertSame((int) $model->getKey(), $legacyCodec->decode($routeKey));
        $this->assertNull($opaqueCodec->decode($routeKey));
    }

    public function test_deprecated_service_provider_keeps_legacy_publish_tag(): void
    {
        $this->app->register(HashedRouteKeyServiceProvider::class);

        $paths = ServiceProvider::pathsToPublish(HashedRouteKeyServiceProvider::class, 'hashed-route-key-config');

        $this->assertNotEmpty($paths);
        $this->assertContains(config_path('hashed-route-key.php'), $paths);
    }
}

class LegacyRouteKeyModel extends Model
{
    use UsesHashedRouteKey;

    protected $table = 'legacy_route_key_models';

    protected $guarded = [];
}
