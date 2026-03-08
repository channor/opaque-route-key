<?php

declare(strict_types=1);

namespace Channor\HashedRouteKey\Tests;

use Channor\HashedRouteKey\UsesHashedRouteKey;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class AppendRouteKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('appendable_models', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('appendable_models');

        parent::tearDown();
    }

    public function test_config_enabled_appends_route_key(): void
    {
        config(['hashed-route-key.append_route_key' => true]);

        $model = AppendDefaultModel::create(['name' => 'test']);
        $array = $model->toArray();

        $this->assertArrayHasKey('route_key', $array);
        $this->assertSame($model->getRouteKey(), $array['route_key']);
    }

    public function test_config_disabled_skips_append(): void
    {
        config(['hashed-route-key.append_route_key' => false]);

        $model = AppendDefaultModel::create(['name' => 'test']);
        $array = $model->toArray();

        $this->assertArrayNotHasKey('route_key', $array);
    }

    public function test_model_property_string_uses_custom_attribute(): void
    {
        $model = AppendPropertyStringModel::create(['name' => 'test']);
        $array = $model->toArray();

        $this->assertArrayHasKey('hash', $array);
        $this->assertArrayNotHasKey('route_key', $array);
    }

    public function test_existing_appends_preserved(): void
    {
        $model = AppendWithExistingAppendsModel::create(['name' => 'test']);
        $array = $model->toArray();

        $this->assertArrayHasKey('route_key', $array);
        $this->assertArrayHasKey('upper_name', $array);
        $this->assertSame('TEST', $array['upper_name']);
    }

    public function test_model_method_override_uses_custom_attribute_name(): void
    {
        $model = AppendMethodCustomModel::create(['name' => 'test']);
        $array = $model->toArray();

        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayNotHasKey('route_key', $array);
    }

    public function test_model_method_override_takes_precedence_over_property(): void
    {
        $model = AppendMethodOverridesPropertyModel::create(['name' => 'test']);
        $array = $model->toArray();

        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayNotHasKey('hash', $array);
    }

    public function test_json_serialization_includes_appended_route_key(): void
    {
        config(['hashed-route-key.append_route_key' => true]);

        $model = AppendDefaultModel::create(['name' => 'test']);
        $json = json_decode($model->toJson(), true);

        $this->assertArrayHasKey('route_key', $json);
        $this->assertSame($model->getRouteKey(), $json['route_key']);
    }
}

class AppendDefaultModel extends Model
{
    use UsesHashedRouteKey;

    protected $table = 'appendable_models';

    protected $guarded = [];
}

class AppendPropertyStringModel extends Model
{
    use UsesHashedRouteKey;

    protected $table = 'appendable_models';

    protected $guarded = [];

    protected bool|string $appendRouteKey = 'hash';

    protected function hash(): Attribute
    {
        return Attribute::make(get: fn () => $this->getRouteKey());
    }
}

class AppendWithExistingAppendsModel extends Model
{
    use UsesHashedRouteKey;

    protected $table = 'appendable_models';

    protected $guarded = [];

    protected $appends = ['upper_name'];

    protected function upperName(): Attribute
    {
        return Attribute::make(get: fn () => strtoupper($this->name));
    }
}

class AppendMethodCustomModel extends Model
{
    use UsesHashedRouteKey;

    protected $table = 'appendable_models';

    protected $guarded = [];

    public function appendRouteKey(): bool|string
    {
        return 'slug';
    }

    protected function slug(): Attribute
    {
        return Attribute::make(get: fn () => $this->getRouteKey());
    }
}

class AppendMethodOverridesPropertyModel extends Model
{
    use UsesHashedRouteKey;

    protected $table = 'appendable_models';

    protected $guarded = [];

    protected bool|string $appendRouteKey = 'hash';

    public function appendRouteKey(): bool|string
    {
        return 'slug';
    }

    protected function slug(): Attribute
    {
        return Attribute::make(get: fn () => $this->getRouteKey());
    }

    protected function hash(): Attribute
    {
        return Attribute::make(get: fn () => $this->getRouteKey());
    }
}
