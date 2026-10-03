<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

use Inkvizitoria\MonoParts\Validation\MoneyRule;
use Inkvizitoria\MonoParts\ValueObjects\Money;

final class CreateQrCartRequest extends MonoPartsRequest
{
    public function __construct(private readonly array $payload)
    {
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function rules(): array
    {
        return [
            'qr_id' => ['required', 'string', 'min:1'],
            'store_order_id' => ['required', 'uuid'],
            'result_callback' => ['required', 'url'],
            'products' => ['required', 'array', 'list', 'min:1'],
            'products.*' => ['required', 'array:name,count,sum'],
            'products.*.name' => ['required', 'string', 'min:1', 'max:500'],
            'products.*.count' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'products.*.sum' => ['required', new MoneyRule()],
        ];
    }

    public function validate(array $payload): array
    {
        $validated = parent::validate($payload);
        foreach ($validated['products'] as &$product) {
            $product['count'] = (int) $product['count'];
            $product['sum'] = Money::fromMixed($product['sum']);
        }
        unset($product);

        return $validated;
    }

    public function endpoint(): string
    {
        return '/api/v1/qr/cart';
    }
}
