<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Responses;

/**
 * Response from order create or duplicate.
 */
final class CreateOrderResult
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self((string) ($payload['order_id'] ?? ''));
    }
}
