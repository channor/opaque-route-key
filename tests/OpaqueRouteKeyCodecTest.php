<?php

declare(strict_types=1);

namespace Channor\OpaqueRouteKey\Tests;

use Channor\OpaqueRouteKey\OpaqueRouteKeyCodec;
use PHPUnit\Framework\TestCase;

final class OpaqueRouteKeyCodecTest extends TestCase
{
    public function test_encode_is_deterministic_for_same_id(): void
    {
        $codec = new OpaqueRouteKeyCodec(salt: 'test');

        $this->assertSame($codec->encode(42), $codec->encode(42));
    }

    public function test_roundtrip_for_small_range(): void
    {
        $codec = new OpaqueRouteKeyCodec(salt: 'test');

        for ($id = 0; $id <= 500; $id++) {
            $this->assertSame($id, $codec->decode($codec->encode($id)));
        }
    }

    public function test_rejects_tampered_key(): void
    {
        $codec = new OpaqueRouteKeyCodec(salt: 'test');
        $key = $codec->encode(42);

        $tampered = substr($key, 0, -1).'Z';

        $this->assertNull($codec->decode($tampered));
    }

    public function test_cross_salt_decode_rejects_overwhelming_majority(): void
    {
        $encoder = new OpaqueRouteKeyCodec(salt: 'account', checkLength: 4);
        $decoder = new OpaqueRouteKeyCodec(salt: 'project', checkLength: 4);

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
            "Expected at least 999/1000 cross-salt route keys rejected, got {$nullCount}/{$total}",
        );
    }

    public function test_payload_length_grows_after_base_boundary(): void
    {
        $codec = new OpaqueRouteKeyCodec(salt: 'test', minPayloadLength: 2, checkLength: 4, offsetMultiplier: 1);
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
        (new OpaqueRouteKeyCodec(salt: 'test'))->encode(-1);
    }

    public function test_constructor_rejects_short_alphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OpaqueRouteKeyCodec(salt: 'test', alphabet: 'a');
    }

    public function test_constructor_rejects_duplicate_alphabet_characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OpaqueRouteKeyCodec(salt: 'test', alphabet: 'aabc');
    }

    public function test_constructor_rejects_zero_min_payload_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OpaqueRouteKeyCodec(salt: 'test', minPayloadLength: 0);
    }

    public function test_constructor_rejects_check_length_over_32(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OpaqueRouteKeyCodec(salt: 'test', checkLength: 33);
    }

    public function test_constructor_rejects_offset_multiplier_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OpaqueRouteKeyCodec(salt: 'test', offsetMultiplier: 0);
    }

    public function test_constructor_rejects_reserved_word_max_attempts_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OpaqueRouteKeyCodec(salt: 'test', reservedWordMaxAttempts: 0);
    }

    public function test_constructor_rejects_non_string_reserved_words(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OpaqueRouteKeyCodec(salt: 'test', reservedWords: [123]);
    }

    public function test_decode_rejects_empty_and_too_short_keys(): void
    {
        $codec = new OpaqueRouteKeyCodec(salt: 'test', checkLength: 4);

        $this->assertNull($codec->decode(''));
        $this->assertNull($codec->decode('a'));
        $this->assertNull($codec->decode('ab'));
        $this->assertNull($codec->decode('abc'));
        $this->assertNull($codec->decode('abcd'));
    }

    public function test_decode_rejects_payloads_that_overflow_integer_range(): void
    {
        $codec = new OpaqueRouteKeyCodec(salt: '');
        $payload = str_repeat('9', 20);
        $tag = \Closure::bind(
            function () use ($payload): string {
                return $this->makeTag($payload, $this->attemptStates[0]);
            },
            $codec,
            $codec,
        )();

        $this->assertNull($codec->decode($payload.$tag));
    }

    public function test_empty_reserved_words_keep_existing_output(): void
    {
        $baseline = new OpaqueRouteKeyCodec(salt: 'test');
        $reserved = new OpaqueRouteKeyCodec(salt: 'test', reservedWords: []);

        $this->assertSame($baseline->encode(42), $reserved->encode(42));
    }

    public function test_non_colliding_reserved_words_keep_existing_output(): void
    {
        $baseline = new OpaqueRouteKeyCodec(salt: 'test');
        $reserved = new OpaqueRouteKeyCodec(salt: 'test', reservedWords: ['create', 'edit']);

        $this->assertSame($baseline->encode(42), $reserved->encode(42));
    }

    public function test_reserved_words_are_case_sensitive_by_default(): void
    {
        $baseline = new OpaqueRouteKeyCodec(salt: 'test');
        $baselineKey = $baseline->encode(42);
        $reserved = new OpaqueRouteKeyCodec(salt: 'test', reservedWords: [strtolower($baselineKey)]);

        $this->assertSame($baselineKey, $reserved->encode(42));
    }

    public function test_reserved_words_can_be_case_insensitive(): void
    {
        $baseline = new OpaqueRouteKeyCodec(salt: 'test');
        $baselineKey = $baseline->encode(42);
        $reserved = new OpaqueRouteKeyCodec(
            salt: 'test',
            reservedWords: [strtolower($baselineKey)],
            reservedWordsCaseSensitive: false,
        );

        $this->assertNotSame($baselineKey, $reserved->encode(42));
    }

    public function test_case_insensitive_reserved_words_ignore_manual_case_sensitivity(): void
    {
        $baseline = new OpaqueRouteKeyCodec(salt: 'test');
        $baselineKey = $baseline->encode(42);
        $reserved = new OpaqueRouteKeyCodec(
            salt: 'test',
            reservedWordsCaseSensitive: true,
            caseInsensitiveReservedWords: [strtolower($baselineKey)],
        );

        $this->assertNotSame($baselineKey, $reserved->encode(42));
    }

    public function test_reserved_word_collision_reruns_and_decodes(): void
    {
        $baseline = new OpaqueRouteKeyCodec(salt: 'test');
        $baselineKey = $baseline->encode(42);
        $reserved = new OpaqueRouteKeyCodec(salt: 'test', reservedWords: [$baselineKey]);
        $rerunKey = $reserved->encode(42);

        $this->assertNotSame($baselineKey, $rerunKey);
        $this->assertSame(42, $reserved->decode($rerunKey));
        $this->assertSame(42, $reserved->decode($baselineKey));
    }

    public function test_reserved_word_reruns_stay_unique_and_decodable(): void
    {
        $baseline = new OpaqueRouteKeyCodec(salt: 'test');
        $reservedWords = [];

        for ($id = 0; $id <= 100; $id++) {
            $reservedWords[] = $baseline->encode($id);
        }

        $reserved = new OpaqueRouteKeyCodec(salt: 'test', reservedWords: $reservedWords);
        $seen = [];

        for ($id = 0; $id <= 100; $id++) {
            $key = $reserved->encode($id);

            $this->assertArrayNotHasKey($key, $seen);
            $this->assertNotContains($key, $reservedWords);
            $this->assertSame($id, $reserved->decode($key));

            $seen[$key] = true;
        }
    }

    public function test_decode_keeps_accepting_keys_that_are_now_reserved_case_insensitively(): void
    {
        $baseline = new OpaqueRouteKeyCodec(salt: 'test');
        $baselineKey = $baseline->encode(42);
        $reserved = new OpaqueRouteKeyCodec(
            salt: 'test',
            caseInsensitiveReservedWords: [strtolower($baselineKey)],
        );

        $this->assertSame(42, $reserved->decode($baselineKey));
    }

    public function test_reserved_word_collision_fails_when_attempts_are_exhausted(): void
    {
        $baseline = new OpaqueRouteKeyCodec(salt: 'test');

        $this->expectException(\RuntimeException::class);

        (new OpaqueRouteKeyCodec(
            salt: 'test',
            reservedWords: [$baseline->encode(42)],
            reservedWordMaxAttempts: 1,
        ))->encode(42);
    }
}
