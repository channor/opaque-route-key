<?php

declare(strict_types=1);

namespace Channor\HashedRouteKey\Tests;

use Channor\HashedRouteKey\HashedRouteKeyCodec;
use PHPUnit\Framework\TestCase;

class HashedRouteKeyCodecTest extends TestCase
{
    public function test_encode_is_deterministic_for_same_id(): void
    {
        $codec = new HashedRouteKeyCodec(salt: 'test');

        $this->assertSame($codec->encode(42), $codec->encode(42));
    }

    public function test_roundtrip_for_small_range(): void
    {
        $codec = new HashedRouteKeyCodec(salt: 'test');

        for ($id = 0; $id <= 500; $id++) {
            $this->assertSame($id, $codec->decode($codec->encode($id)));
        }
    }

    public function test_rejects_tampered_hash(): void
    {
        $codec = new HashedRouteKeyCodec(salt: 'test');
        $hash = $codec->encode(42);

        $tampered = substr($hash, 0, -1).'Z';

        $this->assertNull($codec->decode($tampered));
    }

    public function test_cross_salt_decode_rejects_overwhelming_majority(): void
    {
        $encoder = new HashedRouteKeyCodec(salt: 'account', checkLength: 4);
        $decoder = new HashedRouteKeyCodec(salt: 'project', checkLength: 4);

        $nullCount = 0;
        $total = 1000;

        for ($id = 0; $id < $total; $id++) {
            if ($decoder->decode($encoder->encode($id)) === null) {
                $nullCount++;
            }
        }

        $this->assertGreaterThan(
            998,
            $nullCount,
            "Expected at least 999/1000 cross-salt hashes rejected, got {$nullCount}/{$total}",
        );
    }

    public function test_payload_length_grows_after_base_boundary(): void
    {
        $codec = new HashedRouteKeyCodec(salt: 'test', minPayloadLength: 2, checkLength: 4, offsetMultiplier: 1);
        $base = 62;
        $offset = $base ** (2 - 1);
        $maxTwoPayloadId = ($base ** 2 - 1) - $offset;

        // id 0 should be minimum payload length 2 + 4 check chars
        $first = $codec->encode(0);
        $this->assertSame(6, strlen($first));

        // Growth happens after max 2-char payload value minus offset.
        $maxTwoPayload = $codec->encode($maxTwoPayloadId);
        $nextPayload = $codec->encode($maxTwoPayloadId + 1);

        $this->assertSame(6, strlen($maxTwoPayload));
        $this->assertSame(7, strlen($nextPayload));
    }

    public function test_encode_rejects_negative_ids(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HashedRouteKeyCodec(salt: 'test')->encode(-1);
    }

    public function test_constructor_rejects_short_alphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HashedRouteKeyCodec(salt: 'test', alphabet: 'a');
    }

    public function test_constructor_rejects_duplicate_alphabet_characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HashedRouteKeyCodec(salt: 'test', alphabet: 'aabc');
    }

    public function test_constructor_rejects_zero_min_payload_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HashedRouteKeyCodec(salt: 'test', minPayloadLength: 0);
    }

    public function test_constructor_rejects_check_length_over_32(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HashedRouteKeyCodec(salt: 'test', checkLength: 33);
    }

    public function test_constructor_rejects_offset_multiplier_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HashedRouteKeyCodec(salt: 'test', offsetMultiplier: 0);
    }

    public function test_decode_rejects_empty_and_too_short_hashes(): void
    {
        $codec = new HashedRouteKeyCodec(salt: 'test', checkLength: 4);

        $this->assertNull($codec->decode(''));
        $this->assertNull($codec->decode('a'));
        $this->assertNull($codec->decode('ab'));
        $this->assertNull($codec->decode('abc'));
        $this->assertNull($codec->decode('abcd'));
    }

    public function test_decode_rejects_payloads_that_overflow_integer_range(): void
    {
        $codec = new HashedRouteKeyCodec(salt: '');
        $payload = str_repeat('9', 20);
        $tag = \Closure::bind(
            fn () => $this->makeTag($payload),
            $codec,
            $codec,
        )();

        $this->assertNull($codec->decode($payload.$tag));
    }
}
