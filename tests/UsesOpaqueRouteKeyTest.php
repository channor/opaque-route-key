<?php

declare(strict_types=1);

namespace Channor\OpaqueRouteKey\Tests;

use Channor\OpaqueRouteKey\OpaqueRouteKeyCodec;
use Channor\OpaqueRouteKey\UsesOpaqueRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

final class UsesOpaqueRouteKeyTest extends TestCase
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

        $codec = new OpaqueRouteKeyCodec(
            salt: config('opaque-route-key.salt').':fake_model',
            minPayloadLength: 3,
            checkLength: 4,
            offsetMultiplier: 1,
        );

        $this->assertSame((int) $model->getKey(), $codec->decode($model->getRouteKey()));
    }

    public function test_wrong_model_key_resolves_empty(): void
    {
        $model = FakeModel::create(['name' => 'test']);

        $wrongCodec = new OpaqueRouteKeyCodec(salt: config('opaque-route-key.salt').':other_model');
        $wrongKey = $wrongCodec->encode((int) $model->getKey());

        $resolved = (new FakeModel)
            ->resolveRouteBindingQuery(FakeModel::query(), $wrongKey)
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

        // min payload 2 + check 2 => minimum key length 4
        $this->assertGreaterThanOrEqual(4, strlen($model->getRouteKey()));
    }

    public function test_model_avoids_configured_reserved_words(): void
    {
        $reservedCodec = new OpaqueRouteKeyCodec(
            salt: config('opaque-route-key.salt').':fake_model',
            minPayloadLength: 3,
            checkLength: 4,
            offsetMultiplier: 1,
        );
        $reservedRouteKey = $reservedCodec->encode(1);

        config(['opaque-route-key.reserved_words' => [$reservedRouteKey]]);

        $model = FakeModel::create(['name' => 'test']);

        $this->assertNotSame($reservedRouteKey, $model->getRouteKey());
        $this->assertSame((int) $model->getKey(), $reservedCodec->decode($reservedRouteKey));
        $this->assertSame((int) $model->getKey(), $reservedCodec->decode($model->getRouteKey()));
    }

    public function test_model_can_auto_reserve_singular_and_plural_model_names(): void
    {
        config(['opaque-route-key.auto_reserve_model_names' => true]);

        $reservedWords = (fn () => $this->routeKeyCaseInsensitiveReservedWords())->call(new Account);

        $this->assertContains('account', $reservedWords);
        $this->assertContains('accounts', $reservedWords);
    }

    public function test_model_name_auto_reserve_can_force_rerun(): void
    {
        config(['opaque-route-key.auto_reserve_model_names' => true]);

        $baselineCodec = new OpaqueRouteKeyCodec(
            salt: config('opaque-route-key.salt').':auto_reserved_model',
            minPayloadLength: 3,
            checkLength: 4,
            offsetMultiplier: 1,
        );
        $baselineKey = $baselineCodec->encode(1);

        $model = AutoReservedModel::create(['name' => 'test']);

        $this->assertNotSame($baselineKey, $model->getRouteKey());
        $this->assertSame((int) $model->getKey(), $baselineCodec->decode($model->getRouteKey()));
    }

    public function test_model_name_auto_reserve_is_case_insensitive(): void
    {
        config([
            'opaque-route-key.auto_reserve_model_names' => true,
            'opaque-route-key.reserved_words_case_sensitive' => true,
        ]);

        $baselineCodec = new OpaqueRouteKeyCodec(
            salt: config('opaque-route-key.salt').':uppercase_auto_reserved_model',
            minPayloadLength: 3,
            checkLength: 4,
            offsetMultiplier: 1,
        );
        $baselineKey = $baselineCodec->encode(1);

        $model = UppercaseAutoReservedModel::create(['name' => 'test']);

        $this->assertNotSame($baselineKey, $model->getRouteKey());
        $this->assertSame((int) $model->getKey(), $baselineCodec->decode($model->getRouteKey()));
    }

    public function test_model_name_auto_reserve_stays_disabled_by_default(): void
    {
        $model = AutoReservedModel::create(['name' => 'test']);

        $baselineCodec = new OpaqueRouteKeyCodec(
            salt: config('opaque-route-key.salt').':auto_reserved_model',
            minPayloadLength: 3,
            checkLength: 4,
            offsetMultiplier: 1,
        );

        $this->assertSame($baselineCodec->encode(1), $model->getRouteKey());
    }

    public function test_model_uses_configured_route_key_salt_base(): void
    {
        config(['opaque-route-key.salt' => 'custom-opaque-route-key-salt']);

        $model = FakeModel::create(['name' => 'test']);

        $configuredCodec = new OpaqueRouteKeyCodec(
            salt: 'custom-opaque-route-key-salt:fake_model',
            minPayloadLength: 3,
            checkLength: 4,
            offsetMultiplier: 1,
        );

        $defaultCodec = new OpaqueRouteKeyCodec(
            salt: 'different-route-key-salt:fake_model',
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

        $customCodec = new OpaqueRouteKeyCodec(
            salt: config('opaque-route-key.salt').':shared_suffix',
            minPayloadLength: 3,
            checkLength: 4,
            offsetMultiplier: 1,
        );

        $defaultCodec = new OpaqueRouteKeyCodec(
            salt: config('opaque-route-key.salt').':custom_salt_suffix_model',
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
    use UsesOpaqueRouteKey;

    protected $table = 'fake_models';

    protected $guarded = [];
}

class UuidFakeModel extends Model
{
    use UsesOpaqueRouteKey;

    protected $table = 'fake_models';

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;
}

class CustomStrategyModel extends Model
{
    use UsesOpaqueRouteKey;

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
    use UsesOpaqueRouteKey;

    protected $table = 'fake_models';

    protected $guarded = [];

    protected function routeKeySaltSuffix(): string
    {
        return 'shared_suffix';
    }
}

class Account extends Model
{
    use UsesOpaqueRouteKey;

    protected $table = 'fake_models';

    protected $guarded = [];
}

class AutoReservedModel extends Model
{
    use UsesOpaqueRouteKey;

    protected $table = 'fake_models';

    protected $guarded = [];

    /**
     * @return array<array-key, string>
     */
    protected function routeKeyReservedModelNames(): array
    {
        return [
            strtolower((new OpaqueRouteKeyCodec(
                salt: config('opaque-route-key.salt').':auto_reserved_model',
                minPayloadLength: 3,
                checkLength: 4,
                offsetMultiplier: 1,
            ))->encode(1)),
        ];
    }
}

class UppercaseAutoReservedModel extends Model
{
    use UsesOpaqueRouteKey;

    protected $table = 'fake_models';

    protected $guarded = [];

    /**
     * @return array<array-key, string>
     */
    protected function routeKeyReservedModelNames(): array
    {
        return [
            strtoupper((new OpaqueRouteKeyCodec(
                salt: config('opaque-route-key.salt').':uppercase_auto_reserved_model',
                minPayloadLength: 3,
                checkLength: 4,
                offsetMultiplier: 1,
            ))->encode(1)),
        ];
    }
}
