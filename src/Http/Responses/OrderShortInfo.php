<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Responses;

use DateTimeImmutable;

/**
 * Short order info returned by /api/order/data.
 */
final class OrderShortInfo
{
    /**
     * @param array<int, ReverseEntry> $reverseList
     */
    public function __construct(
        public readonly ?DateTimeImmutable $createTimestamp,
        public readonly ?string $iban,
        public readonly ?string $invoiceDate,
        public readonly ?string $invoiceNumber,
        public readonly ?string $maskedCard,
        public readonly ?string $pointId,
        public readonly array $reverseList,
        public readonly ?string $source,
        public readonly ?string $storeOrderId,
        public readonly ?float $totalSum,
    ) {
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public static function fromPayload(?array $payload): self
    {
        $reverseEntries = [];
        if (is_array($payload) && isset($payload['reverse_list']) && is_array($payload['reverse_list'])) {
            foreach ($payload['reverse_list'] as $entry) {
                if (is_array($entry)) {
                    $reverseEntries[] = ReverseEntry::fromPayload($entry);
                }
            }
        }

        $createTimestamp = null;
        if (is_array($payload) && isset($payload['create_timestamp']) && is_string($payload['create_timestamp'])) {
            $createTimestamp = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $payload['create_timestamp'])
                ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $payload['create_timestamp']);
        }

        return new self(
            createTimestamp: $createTimestamp ?: null,
            iban: is_array($payload) ? ($payload['iban'] ?? null) : null,
            invoiceDate: is_array($payload) ? ($payload['invoice_date'] ?? null) : null,
            invoiceNumber: is_array($payload) ? ($payload['invoice_number'] ?? null) : null,
            maskedCard: is_array($payload) ? ($payload['maskedCard'] ?? null) : null,
            pointId: is_array($payload) ? ($payload['point_id'] ?? null) : null,
            reverseList: $reverseEntries,
            source: is_array($payload) ? ($payload['source'] ?? null) : null,
            storeOrderId: is_array($payload) ? ($payload['store_order_id'] ?? null) : null,
            totalSum: is_array($payload) && isset($payload['total_sum']) ? (float) $payload['total_sum'] : null,
        );
    }
}
