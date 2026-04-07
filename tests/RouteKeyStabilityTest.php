<?php

declare(strict_types=1);

namespace Channor\OpaqueRouteKey\Tests;

use Channor\OpaqueRouteKey\OpaqueRouteKeyCodec;
use PHPUnit\Framework\TestCase;

class RouteKeyStabilityTest extends TestCase
{
    public function test_v1_route_key_outputs_remain_stable(): void
    {
        $codec = new OpaqueRouteKeyCodec(salt: 'test');

        $expected = [
            0 => 'lJJwPbu',
            1 => 'lJle2zQ',
            2 => 'lJxFx69',
            42 => 'lJmMMgc',
            500 => 'lYemSSw',
        ];

        foreach ($expected as $id => $routeKey) {
            $this->assertSame($routeKey, $codec->encode($id));
            $this->assertSame($id, $codec->decode($routeKey));
        }
    }
}
