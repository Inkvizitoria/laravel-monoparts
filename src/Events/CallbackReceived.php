<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Events;

/**
 * @psalm-immutable
 */
final class CallbackReceived
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly ?string $signature = null,
    ) {
    }
}
