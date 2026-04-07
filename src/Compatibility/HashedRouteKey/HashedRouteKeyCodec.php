<?php

declare(strict_types=1);

namespace Channor\HashedRouteKey;

use Channor\OpaqueRouteKey\OpaqueRouteKeyCodec;

/**
 * @deprecated Use Channor\OpaqueRouteKey\OpaqueRouteKeyCodec instead.
 */
readonly class HashedRouteKeyCodec extends OpaqueRouteKeyCodec
{
    public function decode(string $hash): ?int
    {
        return parent::decode($hash);
    }
}
