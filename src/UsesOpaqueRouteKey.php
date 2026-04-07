<?php

declare(strict_types=1);

namespace Channor\OpaqueRouteKey;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @mixin Model
 */
trait UsesOpaqueRouteKey
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

    protected function routeKeyCodec(): OpaqueRouteKeyCodec
    {
        return new OpaqueRouteKeyCodec(
            salt: $this->routeKeyCodecSalt(),
            minPayloadLength: $this->routeKeyMinPayloadLength(),
            checkLength: $this->routeKeyCheckLength(),
            offsetMultiplier: $this->routeKeyOffsetMultiplier(),
            reservedWords: $this->routeKeyReservedWords(),
            reservedWordsCaseSensitive: $this->routeKeyReservedWordsCaseSensitive(),
            caseInsensitiveReservedWords: $this->routeKeyCaseInsensitiveReservedWords(),
            reservedWordMaxAttempts: $this->routeKeyReservedWordMaxAttempts(),
        );
    }

    protected function routeKeyCodecSalt(): string
    {
        return $this->routeKeyConfig('salt').':'.$this->routeKeySaltSuffix();
    }

    protected function routeKeySaltSuffix(): string
    {
        return Str::snake(class_basename(static::class));
    }

    protected function routeKeyMinPayloadLength(): int
    {
        return (int) $this->routeKeyConfig('min_payload_length', 3);
    }

    protected function routeKeyCheckLength(): int
    {
        return (int) $this->routeKeyConfig('check_length', 4);
    }

    protected function routeKeyOffsetMultiplier(): int
    {
        return (int) $this->routeKeyConfig('offset_multiplier', 1);
    }

    /**
     * @return array<array-key, string>
     */
    protected function routeKeyReservedWords(): array
    {
        $reservedWords = $this->routeKeyConfig('reserved_words', []);

        if (! is_array($reservedWords)) {
            throw new \RuntimeException('Route key reserved words must be configured as an array.');
        }

        return $reservedWords;
    }

    /**
     * @return array<array-key, string>
     */
    protected function routeKeyCaseInsensitiveReservedWords(): array
    {
        return $this->routeKeyAutoReserveModelNames() ? $this->routeKeyReservedModelNames() : [];
    }

    /**
     * @return array<array-key, string>
     */
    protected function routeKeyReservedModelNames(): array
    {
        $singular = Str::singular(Str::lower(class_basename(static::class)));
        $plural = Str::plural($singular);

        return array_values(array_unique([$singular, $plural]));
    }

    protected function routeKeyReservedWordsCaseSensitive(): bool
    {
        return (bool) $this->routeKeyConfig('reserved_words_case_sensitive', true);
    }

    protected function routeKeyAutoReserveModelNames(): bool
    {
        return (bool) $this->routeKeyConfig('auto_reserve_model_names', false);
    }

    protected function routeKeyReservedWordMaxAttempts(): int
    {
        return (int) $this->routeKeyConfig('reserved_word_max_attempts', 10);
    }

    public function getRouteKey(): string
    {
        $key = $this->getKey();

        if (filter_var($key, FILTER_VALIDATE_INT) === false) {
            throw new \RuntimeException(
                static::class.' uses '.$this->routeKeyTraitName().' which requires an integer primary key.'
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

        return $this->routeKeyConfig('append_route_key', true) ? $this->defaultRouteKeyAttributeName() : false;
    }

    protected function routeKeyConfigName(): string
    {
        return 'opaque-route-key';
    }

    protected function routeKeyTraitName(): string
    {
        return UsesOpaqueRouteKey::class;
    }

    private function routeKeyConfig(string $key, mixed $default = null): mixed
    {
        return config($this->routeKeyConfigName().'.'.$key, $default);
    }

    private function defaultRouteKeyAttributeName(): string
    {
        return $this->routeKeyConfig('default_attribute_name') ?: 'route_key';
    }
}
