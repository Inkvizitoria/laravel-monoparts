<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Events;

/**
 * @psalm-immutable
 */
final class RequestSending
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly array $payload,
    ) {
    }
}
