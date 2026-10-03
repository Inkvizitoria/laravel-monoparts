<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

use Inkvizitoria\MonoParts\Validation\MoneyRule;
use Inkvizitoria\MonoParts\ValueObjects\Money;

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
        private readonly Money|int|float|string $sum,
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
            'sum' => ['required', new MoneyRule()],
            'additional_params' => ['nullable', 'array:nds'],
            'additional_params.nds' => ['nullable', new MoneyRule(0)],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function validate(array $payload): array
    {
        $validated = parent::validate($payload);
        $validated['sum'] = Money::fromMixed($validated['sum']);

        if (isset($validated['additional_params']['nds'])) {
            $validated['additional_params']['nds'] = Money::fromMixed($validated['additional_params']['nds']);
        }

        if (isset($validated['additional_params'])) {
            $validated['additional_params'] = array_filter($validated['additional_params'], static fn ($value) => $value !== null);
        }
        if (empty($validated['additional_params'])) {
            unset($validated['additional_params']);
        }

        return $validated;
    }

    /**
     * Endpoint path.
     */
    public function endpoint(): string
    {
        return '/api/order/return';
    }
}
