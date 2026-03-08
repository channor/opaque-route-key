<?php

declare(strict_types=1);

namespace Channor\HashedRouteKey;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait UsesHashedRouteKey
{
    protected function getArrayableAppends(): array
    {
        // This trait needs to opt models into appending the computed route key at serialization time.
        // Overriding Eloquent's append resolution is the narrowest place to do that from a trait.
        if ($attributeName = $this->appendRouteKey()) {
            return array_merge(
                parent::getArrayableAppends(),
                [$attributeName]
            );
        }

        return parent::getArrayableAppends();
    }

    protected function routeKey(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?? ($this->exists ? $this->getRouteKey() : null),
        );
    }

    protected function routeKeyCodec(): HashedRouteKeyCodec
    {
        return new HashedRouteKeyCodec(
            salt: $this->routeKeyCodecSalt(),
            minPayloadLength: $this->routeKeyMinPayloadLength(),
            checkLength: $this->routeKeyCheckLength(),
            offsetMultiplier: $this->routeKeyOffsetMultiplier(),
        );
    }

    protected function routeKeyCodecSalt(): string
    {
        return config('hashed-route-key.salt').':'.$this->routeKeySaltSuffix();
    }

    protected function routeKeySaltSuffix(): string
    {
        return Str::snake(class_basename(static::class));
    }

    protected function routeKeyMinPayloadLength(): int
    {
        return (int) config('hashed-route-key.min_payload_length', 3);
    }

    protected function routeKeyCheckLength(): int
    {
        return (int) config('hashed-route-key.check_length', 4);
    }

    protected function routeKeyOffsetMultiplier(): int
    {
        return (int) config('hashed-route-key.offset_multiplier', 1);
    }

    public function getRouteKey(): string
    {
        $key = $this->getKey();

        if (filter_var($key, FILTER_VALIDATE_INT) === false) {
            throw new \RuntimeException(
                static::class.' uses UsesHashedRouteKey which requires an integer primary key.'
            );
        }

        return $this->routeKeyCodec()->encode((int) $key);
    }

    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        if ($field !== null && $field !== $this->getKeyName()) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        $id = is_string($value)
            ? $this->routeKeyCodec()->decode($value)
            : null;

        // Auto-incrementing keys are positive, so `-1` forces an empty result when decode fails.
        return $query->where($this->getKeyName(), $id ?? -1);
    }

    public function appendRouteKey(): bool|string
    {
        if (property_exists($this, 'appendRouteKey')) {
            return $this->appendRouteKey === true ? $this->defaultRouteKeyAttributeName() : $this->appendRouteKey;
        }

        return config('hashed-route-key.append_route_key') ? $this->defaultRouteKeyAttributeName() : false;
    }

    private function defaultRouteKeyAttributeName(): string
    {
        return config('hashed-route-key.default_attribute_name') ?: 'route_key';
    }
}
