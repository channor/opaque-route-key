<?php

declare(strict_types=1);

namespace Channor\HashedRouteKey;

use InvalidArgumentException;

readonly class HashedRouteKeyCodec
{
    private string $shuffledAlphabet;

    private int $base;

    private int $offset;

    private string $tagKey;

    public function __construct(
        string $salt,
        string $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        private int $minPayloadLength = 3,
        private int $checkLength = 4,
        int $offsetMultiplier = 1,
    ) {
        if (strlen($alphabet) < 2) {
            throw new InvalidArgumentException('Alphabet must contain at least 2 characters.');
        }

        if (count(array_unique(str_split($alphabet))) !== strlen($alphabet)) {
            throw new InvalidArgumentException('Alphabet must not contain duplicate characters.');
        }

        if ($minPayloadLength < 1) {
            throw new InvalidArgumentException('Minimum payload length must be at least 1.');
        }

        if ($checkLength < 1) {
            throw new InvalidArgumentException('Check length must be at least 1.');
        }

        if ($checkLength > 32) {
            throw new InvalidArgumentException('Check length must not exceed 32.');
        }

        if ($offsetMultiplier < 1) {
            throw new InvalidArgumentException('Offset multiplier must be at least 1.');
        }

        $saltHash = $salt !== '' ? hash('sha256', $salt) : '';

        $this->shuffledAlphabet = $this->shuffle($alphabet, $saltHash);
        $this->base = strlen($this->shuffledAlphabet);
        $this->offset = $offsetMultiplier * ((int) $this->base ** ($this->minPayloadLength - 1));
        $this->tagKey = hash('sha256', 'hashed-route-key|'.$salt, true);
    }

    public function encode(int $id): string
    {
        if ($id < 0) {
            throw new InvalidArgumentException('ID must be a non-negative integer.');
        }

        $payload = $this->encodeNumber($id + $this->offset);

        return $payload.$this->makeTag($payload);
    }

    public function decode(string $hash): ?int
    {
        if (strlen($hash) <= $this->checkLength) {
            return null;
        }

        $payload = substr($hash, 0, -$this->checkLength);
        $tag = substr($hash, -$this->checkLength);

        if ($payload === '' || ! hash_equals($this->makeTag($payload), $tag)) {
            return null;
        }

        $number = $this->decodePayload($payload);

        if ($number === null) {
            return null;
        }

        $id = $number - $this->offset;

        return $id >= 0 ? $id : null;
    }

    private function encodeNumber(int $number): string
    {
        $encoded = '';

        do {
            $encoded = $this->shuffledAlphabet[$number % $this->base].$encoded;
            $number = intdiv($number, $this->base);
        } while ($number > 0);

        return $encoded;
    }

    private function decodePayload(string $payload): ?int
    {
        $number = 0;

        for ($i = 0; $i < strlen($payload); $i++) {
            $pos = strpos($this->shuffledAlphabet, $payload[$i]);

            if ($pos === false) {
                return null;
            }

            if ($number > intdiv(PHP_INT_MAX - $pos, $this->base)) {
                return null;
            }

            $number = $number * $this->base + $pos;
        }

        return $number;
    }

    private function makeTag(string $payload): string
    {
        $mac = hash_hmac('sha256', $payload, $this->tagKey, true);
        $tag = '';

        for ($i = 0; $i < $this->checkLength; $i++) {
            $tag .= $this->shuffledAlphabet[ord($mac[$i]) % $this->base];
        }

        return $tag;
    }

    private function shuffle(string $alphabet, string $salt): string
    {
        if ($salt === '') {
            return $alphabet;
        }

        $chars = str_split($alphabet);
        $saltLength = strlen($salt);

        for ($i = count($chars) - 1, $v = 0, $p = 0; $i > 0; $i--, $v++) {
            $v %= $saltLength;
            $p += $int = ord($salt[$v]);
            $j = ($int + $v + $p) % ($i + 1);

            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
