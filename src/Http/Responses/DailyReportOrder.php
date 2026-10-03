<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Responses;

use DateTimeImmutable;
use Inkvizitoria\MonoParts\ValueObjects\Money;

/**
 * Single order entry in a daily report.
 */
final class DailyReportOrder
{
    public function __construct(
        public readonly ?string $cardNumber,
        public readonly ?Money $commission,
        public readonly ?float $commissionPercent,
        public readonly ?DateTimeImmutable $createDateTime,
        public readonly ?Money $creditSum,
        public readonly ?string $invoiceNumber,
        public readonly ?string $odbContractNumber,
        public readonly ?DateTimeImmutable $operationTimestamp,
        public readonly ?string $orderDate,
        public readonly ?string $orderId,
        public readonly ?int $payParts,
        public readonly ?string $paymentDate,
        public readonly ?Money $sentSum,
        public readonly ?string $terminalId,
        public readonly ?Money $totalSum,
        public readonly ?string $transactionDate,
        public readonly ?string $transactionId,
        public readonly ?Money $transferredSum,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            cardNumber: $payload['card_number'] ?? null,
            commission: Money::tryFromMixed($payload['commission'] ?? null),
            commissionPercent: isset($payload['commission_percent']) ? (float) $payload['commission_percent'] : null,
            createDateTime: isset($payload['create_date_time']) && is_string($payload['create_date_time'])
                ? (DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $payload['create_date_time'])
                    ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $payload['create_date_time']) ?: null)
                : null,
            creditSum: Money::tryFromMixed($payload['credit_sum'] ?? null),
            invoiceNumber: $payload['invoice_number'] ?? null,
            odbContractNumber: $payload['odb_contract_number'] ?? null,
            operationTimestamp: isset($payload['operation_timestamp']) && is_string($payload['operation_timestamp'])
                ? (DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $payload['operation_timestamp'])
                    ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $payload['operation_timestamp']) ?: null)
                : null,
            orderDate: $payload['order_date'] ?? null,
            orderId: $payload['order_id'] ?? null,
            payParts: isset($payload['pay_parts']) ? (int) $payload['pay_parts'] : null,
            paymentDate: $payload['payment_date'] ?? null,
            sentSum: Money::tryFromMixed($payload['sent_sum'] ?? null),
            terminalId: $payload['terminal_id'] ?? null,
            totalSum: Money::tryFromMixed($payload['total_sum'] ?? null),
            transactionDate: $payload['transaction_date'] ?? null,
            transactionId: $payload['transaction_id'] ?? null,
            transferredSum: Money::tryFromMixed($payload['transferred_sum'] ?? null),
        );
    }
}
