<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

/**
 * Request definition for /api/order/return.
 */
final class ReturnOrderRequest extends OrderIdRequest
{
    /**
     * @param array<string, mixed> $additionalParams
     */
    public function __construct(
        string $orderId,
        private readonly float $sum,
        private readonly bool $returnMoneyToCard,
        private readonly string $storeReturnId,
        private readonly array $additionalParams = [],
    ) {
        parent::__construct($orderId);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $base = parent::payload();
        $base['return_money_to_card'] = $this->returnMoneyToCard;
        $base['store_return_id'] = $this->storeReturnId;
        $base['sum'] = $this->sum;

        if ($this->additionalParams !== []) {
            $base['additional_params'] = $this->additionalParams;
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'return_money_to_card' => ['required', 'boolean'],
            'store_return_id' => ['required', 'string', 'min:1'],
            'sum' => ['required', 'numeric', 'min:0.01', 'regex:/^\\d+(\\.\\d{1,2})?$/'],
            'additional_params' => ['nullable', 'array'],
            'additional_params.nds' => ['nullable', 'numeric'],
        ]);
    }

    /**
     * Endpoint path.
     */
    public function endpoint(): string
    {
        return '/api/order/return';
    }
}
