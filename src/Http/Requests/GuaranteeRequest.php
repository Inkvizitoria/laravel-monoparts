<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

abstract class GuaranteeRequest extends OrderIdRequest
{
    /** @param array{date?: string, number?: string} $invoice */
    public function __construct(string $orderId, private readonly array $invoice = [])
    {
        parent::__construct($orderId);
    }

    public function payload(): array
    {
        $payload = parent::payload();
        if ($this->invoice !== []) {
            $payload['invoice'] = $this->invoice;
        }

        return $payload;
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'invoice' => ['sometimes', 'array:date,number'],
            'invoice.date' => ['sometimes', 'date_format:Y-m-d'],
            'invoice.number' => ['sometimes', 'string', 'min:1'],
        ]);
    }
}
