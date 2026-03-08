<?php

declare(strict_types=1);

namespace Channor\HashedRouteKey\Tests;

use Channor\HashedRouteKey\HashedRouteKeyCodec;
use Channor\HashedRouteKey\UsesHashedRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class UsesHashedRouteKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('fake_models', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('fake_models');

        parent::tearDown();
    }

    public function test_get_route_key_encodes_primary_key(): void
    {
        $model = FakeModel::create(['name' => 'test']);

        $codec = new HashedRouteKeyCodec(
            salt: config('hashed-route-key.salt').':fake_model',
            minPayloadLength: 3,
            checkLength: 4,
            offsetMultiplier: 1,
        );

        $this->assertSame((int) $model->getKey(), $codec->decode($model->getRouteKey()));
    }

    public function test_wrong_model_hash_resolves_empty(): void
    {
        $model = FakeModel::create(['name' => 'test']);

        $wrongCodec = new HashedRouteKeyCodec(salt: config('hashed-route-key.salt').':other_model');
        $wrongHash = $wrongCodec->encode((int) $model->getKey());

        $resolved = (new FakeModel)
            ->resolveRouteBindingQuery(FakeModel::query(), $wrongHash)
            ->first();

        $this->assertNull($resolved);
    }

    public function test_non_integer_primary_key_throws(): void
    {
        $model = new UuidFakeModel;
        $model->forceFill(['id' => '550e8400-e29b-41d4-a716-446655440000']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('requires an integer primary key');

        $model->getRouteKey();
    }

    public function test_model_can_override_strategy(): void
    {
        $model = CustomStrategyModel::forceCreate(['id' => 1, 'name' => 'test']);

        // min payload 2 + check 2 => minimum hash length 4
        $this->assertGreaterThanOrEqual(4, strlen($model->getRouteKey()));
    }

    public function test_model_uses_configured_hash_salt_base(): void
    {
        config(['hashed-route-key.salt' => 'custom-hashed-route-key-salt']);

        $model = FakeModel::create(['name' => 'test']);

        $configuredCodec = new HashedRouteKeyCodec(
            salt: 'custom-hashed-route-key-salt:fake_model',
            minPayloadLength: 3,
            checkLength: 4,
            offsetMultiplier: 1,
        );

        $defaultCodec = new HashedRouteKeyCodec(
            salt: 'different-hash-salt:fake_model',
            minPayloadLength: 3,
            checkLength: 4,
            offsetMultiplier: 1,
        );

        $this->assertSame((int) $model->getKey(), $configuredCodec->decode($model->getRouteKey()));
        $this->assertNull($defaultCodec->decode($model->getRouteKey()));
    }

    public function test_unsaved_model_route_key_attribute_is_null(): void
    {
        $model = new FakeModel;

        $this->assertNull($model->route_key);
    }

    public function test_model_can_override_salt_suffix(): void
    {
        $model = CustomSaltSuffixModel::create(['name' => 'test']);

        $customCodec = new HashedRouteKeyCodec(
            salt: config('hashed-route-key.salt').':shared_suffix',
            minPayloadLength: 3,
            checkLength: 4,
            offsetMultiplier: 1,
        );

        $defaultCodec = new HashedRouteKeyCodec(
            salt: config('hashed-route-key.salt').':custom_salt_suffix_model',
            minPayloadLength: 3,
            checkLength: 4,
            offsetMultiplier: 1,
        );

        $this->assertSame((int) $model->getKey(), $customCodec->decode($model->getRouteKey()));
        $this->assertNull($defaultCodec->decode($model->getRouteKey()));
    }

    public function test_resolve_route_binding_with_explicit_field_falls_through(): void
    {
        $model = FakeModel::create(['name' => 'findme']);

        $resolved = (new FakeModel)
            ->resolveRouteBindingQuery(FakeModel::query(), 'findme', 'name')
            ->first();

        $this->assertNotNull($resolved);
        $this->assertTrue($model->is($resolved));
    }
}

class FakeModel extends Model
{
    use UsesHashedRouteKey;

    protected $table = 'fake_models';

    protected $guarded = [];
}

class UuidFakeModel extends Model
{
    use UsesHashedRouteKey;

    protected $table = 'fake_models';

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;
}

class CustomStrategyModel extends Model
{
    use UsesHashedRouteKey;

    protected $table = 'fake_models';

    protected $guarded = [];

    protected function routeKeyMinPayloadLength(): int
    {
        return 2;
    }

    protected function routeKeyCheckLength(): int
    {
        return 2;
    }
}

class CustomSaltSuffixModel extends Model
{
    use UsesHashedRouteKey;

    protected $table = 'fake_models';

    protected $guarded = [];

    protected function routeKeySaltSuffix(): string
    {
        return 'shared_suffix';
    }
}
