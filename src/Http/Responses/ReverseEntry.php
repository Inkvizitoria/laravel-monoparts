<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Responses;

use DateTimeImmutable;

/**
 * Single reverse entry for order short info.
 */
final class ReverseEntry
{
    public function __construct(
        public readonly ?float $sum,
        public readonly ?DateTimeImmutable $timestamp,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        $timestamp = null;
        if (isset($payload['timestamp']) && is_string($payload['timestamp'])) {
            $timestamp = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $payload['timestamp'])
                ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $payload['timestamp']);
        }

        return new self(
            sum: isset($payload['sum']) ? (float) $payload['sum'] : null,
            timestamp: $timestamp ?: null,
        );
    }
}
