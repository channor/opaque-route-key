<?php

declare(strict_types=1);

namespace Channor\OpaqueRouteKey;

use InvalidArgumentException;
use RuntimeException;

readonly class OpaqueRouteKeyCodec
{
    /**
     * @var list<array{alphabet: string, base: int, offset: int, tag_key: string}>
     */
    private array $attemptStates;

    /**
     * @var list<string>
     */
    private array $reservedWords;

    /**
     * @var list<string>
     */
    private array $caseInsensitiveReservedWords;

    /**
     * @param  array<array-key, mixed>  $reservedWords
     * @param  array<array-key, mixed>  $caseInsensitiveReservedWords
     */
    public function __construct(
        string $salt,
        string $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        private int $minPayloadLength = 3,
        private int $checkLength = 4,
        int $offsetMultiplier = 1,
        array $reservedWords = [],
        private bool $reservedWordsCaseSensitive = true,
        array $caseInsensitiveReservedWords = [],
        private int $reservedWordMaxAttempts = 10,
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

        if ($reservedWordMaxAttempts < 1) {
            throw new InvalidArgumentException('Reserved word max attempts must be at least 1.');
        }

        $this->reservedWords = $this->normalizeReservedWords($reservedWords);
        $this->caseInsensitiveReservedWords = $this->normalizeCaseInsensitiveReservedWords($caseInsensitiveReservedWords);
        $this->attemptStates = $this->buildAttemptStates($salt, $alphabet, $offsetMultiplier);
    }

    public function encode(int $id): string
    {
        if ($id < 0) {
            throw new InvalidArgumentException('ID must be a non-negative integer.');
        }

        foreach ($this->attemptStates as $attempt => $state) {
            $key = $this->encodeForState($id, $state);

            if (! $this->isReservedWord($key) && $this->isFirstValidAttempt($key, $attempt)) {
                return $key;
            }
        }

        throw new RuntimeException('Unable to encode route key without colliding with reserved words.');
    }

    public function decode(string $routeKey): ?int
    {
        foreach ($this->attemptStates as $state) {
            $id = $this->decodeForState($routeKey, $state);

            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param  array{alphabet: string, base: int, offset: int, tag_key: string}  $state
     */
    private function encodeForState(int $id, array $state): string
    {
        $payload = $this->encodeNumber($id + $state['offset'], $state);

        return $payload.$this->makeTag($payload, $state);
    }

    /**
     * @param  array{alphabet: string, base: int, offset: int, tag_key: string}  $state
     */
    private function decodeForState(string $routeKey, array $state): ?int
    {
        if (strlen($routeKey) <= $this->checkLength) {
            return null;
        }

        $payload = substr($routeKey, 0, -$this->checkLength);
        $tag = substr($routeKey, -$this->checkLength);

        if ($payload === '' || ! hash_equals($this->makeTag($payload, $state), $tag)) {
            return null;
        }

        $number = $this->decodePayload($payload, $state);

        if ($number === null) {
            return null;
        }

        $id = $number - $state['offset'];

        return $id >= 0 ? $id : null;
    }

    /**
     * @param  array{alphabet: string, base: int, offset: int, tag_key: string}  $state
     */
    private function encodeNumber(int $number, array $state): string
    {
        $encoded = '';

        do {
            $encoded = $state['alphabet'][$number % $state['base']].$encoded;
            $number = intdiv($number, $state['base']);
        } while ($number > 0);

        return $encoded;
    }

    /**
     * @param  array{alphabet: string, base: int, offset: int, tag_key: string}  $state
     */
    private function decodePayload(string $payload, array $state): ?int
    {
        $number = 0;

        for ($i = 0; $i < strlen($payload); $i++) {
            $pos = strpos($state['alphabet'], $payload[$i]);

            if ($pos === false) {
                return null;
            }

            if ($number > intdiv(PHP_INT_MAX - $pos, $state['base'])) {
                return null;
            }

            $number = $number * $state['base'] + $pos;
        }

        return $number;
    }

    /**
     * @param  array{alphabet: string, base: int, offset: int, tag_key: string}  $state
     */
    private function makeTag(string $payload, array $state): string
    {
        $mac = hash_hmac('sha256', $payload, $state['tag_key'], true);
        $tag = '';

        for ($i = 0; $i < $this->checkLength; $i++) {
            $tag .= $state['alphabet'][ord($mac[$i]) % $state['base']];
        }

        return $tag;
    }

    private function isFirstValidAttempt(string $routeKey, int $attempt): bool
    {
        for ($i = 0; $i < $attempt; $i++) {
            if ($this->decodeForState($routeKey, $this->attemptStates[$i]) !== null) {
                return false;
            }
        }

        return true;
    }

    private function isReservedWord(string $key): bool
    {
        return in_array($this->normalizeReservedWord($key), $this->reservedWords, true)
            || in_array(strtolower($key), $this->caseInsensitiveReservedWords, true);
    }

    /**
     * @param  array<array-key, mixed>  $reservedWords
     * @return list<string>
     */
    private function normalizeReservedWords(array $reservedWords): array
    {
        $normalized = [];

        foreach ($reservedWords as $word) {
            if (! is_string($word)) {
                throw new InvalidArgumentException('Reserved words must be strings.');
            }

            $word = $this->normalizeReservedWord($word);

            if ($word === '') {
                continue;
            }

            $normalized[$word] = true;
        }

        return array_keys($normalized);
    }

    /**
     * @param  array<array-key, mixed>  $reservedWords
     * @return list<string>
     */
    private function normalizeCaseInsensitiveReservedWords(array $reservedWords): array
    {
        $normalized = [];

        foreach ($reservedWords as $word) {
            if (! is_string($word)) {
                throw new InvalidArgumentException('Case-insensitive reserved words must be strings.');
            }

            $word = strtolower($word);

            if ($word === '') {
                continue;
            }

            $normalized[$word] = true;
        }

        return array_keys($normalized);
    }

    private function normalizeReservedWord(string $word): string
    {
        return $this->reservedWordsCaseSensitive ? $word : strtolower($word);
    }

    /**
     * @return list<array{alphabet: string, base: int, offset: int, tag_key: string}>
     */
    private function buildAttemptStates(string $salt, string $alphabet, int $offsetMultiplier): array
    {
        $states = [];

        for ($attempt = 0; $attempt < $this->reservedWordMaxAttempts; $attempt++) {
            $attemptSalt = $attempt === 0 ? $salt : $salt.'|reserved-word-attempt:'.$attempt;
            $saltHash = $attemptSalt !== '' ? hash('sha256', $attemptSalt) : '';
            $shuffledAlphabet = $this->shuffle($alphabet, $saltHash);
            $base = strlen($shuffledAlphabet);

            $states[] = [
                'alphabet' => $shuffledAlphabet,
                'base' => $base,
                'offset' => $offsetMultiplier * ((int) $base ** ($this->minPayloadLength - 1)),
                // Keep the attempt-0 domain unchanged so v1.* route keys remain stable.
                'tag_key' => hash('sha256', 'hashed-route-key|'.$attemptSalt, true),
            ];
        }

        return $states;
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
