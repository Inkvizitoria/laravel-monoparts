<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

abstract class OrderIdRequest extends MonoPartsRequest
{
    public function __construct(
        protected readonly string $orderId,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return ['order_id' => $this->orderId];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'string', 'min:1', 'max:100', 'regex:/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/'],
        ];
    }
}
