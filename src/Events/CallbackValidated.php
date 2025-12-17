<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Events;

use Inkvizitoria\MonoParts\Http\Responses\OrderStateInfo;

/**
 * @psalm-immutable
 */
final class CallbackValidated
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly string $signature,
        public readonly ?OrderStateInfo $stateInfo = null,
    ) {
    }
}
