<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Security;

use Illuminate\Contracts\Support\Arrayable;
use Inkvizitoria\MonoParts\ValueObjects\Money;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Deterministic JSON body builder for signing.
 */
final class JsonBodyBuilder
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * Build deterministic JSON string for signing and transport.
     *
     * @param array<string, mixed>|Arrayable|JsonSerializable $payload
     */
    public function build(array|Arrayable|JsonSerializable $payload): string
    {
        $normalized = $this->normalize($this->toArray($payload));

        return $normalized === [] ? '{}' : $this->encode($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(array|Arrayable|JsonSerializable $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if ($payload instanceof Arrayable) {
            return $payload->toArray();
        }

        $serialized = $payload->jsonSerialize();
        if (!is_array($serialized)) {
            throw new InvalidArgumentException('JsonSerializable payload must return an array.');
        }

        return $serialized;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload): array
    {
        if ($this->isAssoc($payload)) {
            ksort($payload);
        }

        foreach ($payload as $key => $value) {
            $payload[$key] = $this->normalizeValue($value);
        }

        return $payload;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof Money) {
            return new RawJsonNumber($value->toDecimal());
        }

        if ($value instanceof RawJsonNumber) {
            return $value;
        }

        if (is_array($value)) {
            return $this->normalize($value);
        }

        if ($value instanceof Arrayable) {
            return $this->normalize($value->toArray());
        }

        if ($value instanceof JsonSerializable) {
            $serialized = $value->jsonSerialize();
            if (!is_array($serialized)) {
                throw new InvalidArgumentException('JsonSerializable payload must return an array.');
            }

            return $this->normalize($serialized);
        }

        if (is_float($value)) {
            throw new InvalidArgumentException('Float values are not allowed in JSON body builder.');
        }

        return $value;
    }

    private function encode(mixed $value): string
    {
        if ($value instanceof RawJsonNumber) {
            return $value->value();
        }

        if (is_array($value)) {
            return $this->encodeArray($value);
        }

        if (is_string($value)) {
            return json_encode($value, self::JSON_FLAGS);
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        throw new InvalidArgumentException('Unsupported value type in JSON body builder.');
    }

    /**
     * @param array<string, mixed> $value
     */
    private function encodeArray(array $value): string
    {
        $chunks = [];

        if ($this->isAssoc($value)) {
            ksort($value);
            foreach ($value as $key => $item) {
                $encodedKey = json_encode((string) $key, self::JSON_FLAGS);
                $chunks[] = $encodedKey . ':' . $this->encode($item);
            }

            return '{' . implode(',', $chunks) . '}';
        }

        foreach ($value as $item) {
            $chunks[] = $this->encode($item);
        }

        return '[' . implode(',', $chunks) . ']';
    }

    private function isAssoc(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
