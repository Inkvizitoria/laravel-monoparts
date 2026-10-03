<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

use Inkvizitoria\MonoParts\Validation\MoneyRule;
use Inkvizitoria\MonoParts\ValueObjects\Money;

/**
 * Request definition for broker installment availability.
 */
final class BrokerAvailabilityRequest extends MonoPartsRequest
{
    public function __construct(
        private readonly Money|int|float|string $amount,
        private readonly string $employeeId,
        private readonly string $inn,
        private readonly string $outletId,
        private readonly string $phone,
        private readonly ?string $brokerId = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'amount' => $this->amount,
            'employeeID' => $this->employeeId,
            'inn' => $this->inn,
            'outletID' => $this->outletId,
            'phone' => $this->phone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', new MoneyRule()],
            'employeeID' => ['required', 'string', 'min:1'],
            'inn' => ['required', 'string', 'min:1'],
            'outletID' => ['required', 'string', 'min:1'],
            'phone' => ['required', 'string', 'regex:/^\\+380\\d{9}$/'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function validate(array $payload): array
    {
        $validated = parent::validate($payload);
        $validated['amount'] = Money::fromMixed($validated['amount']);

        return $validated;
    }

    public function requiresStoreId(): bool
    {
        return false;
    }

    public function requiresBrokerId(): bool
    {
        return true;
    }

    public function brokerId(): ?string
    {
        return $this->brokerId;
    }

    /**
     * Endpoint path.
     */
    public function endpoint(): string
    {
        return '/api/fin/broker/check/installment/availability';
    }
}
