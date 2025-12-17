<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Responses;

/**
 * Daily report listing orders.
 */
final class DailyReport
{
    /**
     * @param array<int, DailyReportOrder> $orders
     */
    public function __construct(
        public readonly array $orders,
    ) {
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public static function fromPayload(?array $payload): self
    {
        $orders = [];

        if (is_array($payload) && isset($payload['orders']) && is_array($payload['orders'])) {
            foreach ($payload['orders'] as $order) {
                if (is_array($order)) {
                    $orders[] = DailyReportOrder::fromPayload($order);
                }
            }
        }

        return new self($orders);
    }
}
