<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Responses;

/**
 * Result of /api/order/check/paid call.
 */
final class CheckPaidResult
{
    public function __construct(
        public readonly bool $fullyPaid,
        public readonly bool $bankCanReturnMoneyToCard,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            fullyPaid: (bool) ($payload['fully_paid'] ?? false),
            bankCanReturnMoneyToCard: (bool) ($payload['bank_can_return_money_to_card'] ?? false),
        );
    }
}
