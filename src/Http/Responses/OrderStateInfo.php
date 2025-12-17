<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Responses;

use Inkvizitoria\MonoParts\Enums\OrderState;
use Inkvizitoria\MonoParts\Enums\OrderSubState;

/**
 * Typed representation of OrderStateInfo schema.
 */
final class OrderStateInfo
{
    public function __construct(
        public readonly ?string $message,
        public readonly ?string $orderId,
        public readonly ?OrderSubState $orderSubState,
        public readonly ?OrderState $state,
        public readonly ?string $rawOrderSubState = null,
        public readonly ?string $rawState = null,
    ) {
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public static function fromPayload(?array $payload): self
    {
        $orderSubStateRaw = is_array($payload) ? ($payload['order_sub_state'] ?? null) : null;
        $stateRaw = is_array($payload) ? ($payload['state'] ?? null) : null;

        return new self(
            message: is_array($payload) ? ($payload['message'] ?? null) : null,
            orderId: is_array($payload) ? ($payload['order_id'] ?? null) : null,
            orderSubState: OrderSubState::tryFromString($orderSubStateRaw),
            state: $stateRaw ? OrderState::tryFrom($stateRaw) : null,
            rawOrderSubState: is_string($orderSubStateRaw) ? $orderSubStateRaw : null,
            rawState: is_string($stateRaw) ? $stateRaw : null,
        );
    }
}
