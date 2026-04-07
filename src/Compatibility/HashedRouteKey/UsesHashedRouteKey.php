<?php

declare(strict_types=1);

namespace Channor\HashedRouteKey;

use Channor\OpaqueRouteKey\UsesOpaqueRouteKey;

/**
 * @deprecated Use Channor\OpaqueRouteKey\UsesOpaqueRouteKey instead.
 */
trait UsesHashedRouteKey
{
    use UsesOpaqueRouteKey;

    protected function routeKeyCodec(): HashedRouteKeyCodec
    {
        return new HashedRouteKeyCodec(
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

    protected function routeKeyConfigName(): string
    {
        return 'hashed-route-key';
    }

    protected function routeKeyTraitName(): string
    {
        return UsesHashedRouteKey::class;
    }
}
