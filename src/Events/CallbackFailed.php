<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Events;

use Throwable;

/**
 * @psalm-immutable
 */
final class CallbackFailed
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly ?string $signature,
        public readonly Throwable $exception,
    ) {
    }
}
