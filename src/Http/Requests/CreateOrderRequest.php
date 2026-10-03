<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

use Inkvizitoria\MonoParts\Validation\MoneyRule;
use Inkvizitoria\MonoParts\ValueObjects\Money;

/**
 * Request definition for /api/order/create.
 */
final class CreateOrderRequest extends MonoPartsRequest
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'store_order_id' => ['required', 'string', 'min:1', 'max:64'],
            'client_phone' => ['required', 'string', 'regex:/^\\+380\\d{9}$/'],
            'total_sum' => ['required', new MoneyRule(100)],
            'invoice' => ['required', 'array:date,number,point_id,source'],
            'invoice.date' => ['required', 'date_format:Y-m-d'],
            'invoice.number' => ['required', 'string', 'min:1'],
            'invoice.point_id' => ['nullable', 'string', 'min:1', 'max:50'],
            'invoice.source' => ['required', 'in:STORE,INTERNET,CHECKOUT'],
            'available_programs' => ['required', 'array', 'list', 'min:1'],
            'available_programs.*' => ['required', 'array:available_parts_count,type'],
            'available_programs.*.available_parts_count' => ['required', 'array', 'list', 'min:1'],
            'available_programs.*.available_parts_count.*' => ['integer', 'min:1', 'max:2147483647'],
            'available_programs.*.type' => ['required', 'string', 'regex:/^payment_installments$/'],
            'products' => ['required', 'array', 'list', 'min:1'],
            'products.*' => ['required', 'array:name,count,sum'],
            'products.*.name' => ['required', 'string', 'min:1', 'max:500'],
            'products.*.count' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'products.*.sum' => ['required', new MoneyRule()],
            'result_callback' => ['nullable', 'url'],
            'financial_company_merchant_info' => ['nullable', 'array:edrpou_code,iban_account,store_name'],
            'financial_company_merchant_info.edrpou_code' => ['nullable', 'string', 'regex:/^\\d+$/'],
            'financial_company_merchant_info.iban_account' => ['nullable', 'string', 'regex:/^UA\\d{27}$/'],
            'financial_company_merchant_info.store_name' => ['nullable', 'string'],
            'additional_params' => ['nullable', 'array:nds,seller_phone,ext_initial_sum'],
            'additional_params.nds' => ['nullable', new MoneyRule(0)],
            'additional_params.seller_phone' => ['nullable', 'string', 'regex:/^\\+380\\d{9}$/'],
            'additional_params.ext_initial_sum' => ['nullable', new MoneyRule(0)],
        ];
    }

    public function validate(array $payload): array
    {
        $validated = parent::validate($payload);
        $validated['total_sum'] = Money::fromMixed($validated['total_sum']);
        foreach ($validated['products'] as &$product) {
            $product['count'] = (int) $product['count'];
            $product['sum'] = Money::fromMixed($product['sum']);
        }
        unset($product);
        foreach ($validated['available_programs'] as &$program) {
            $program['available_parts_count'] = array_map('intval', $program['available_parts_count']);
        }
        unset($program);
        foreach (['nds', 'ext_initial_sum'] as $field) {
            if (isset($validated['additional_params'][$field])) {
                $validated['additional_params'][$field] = Money::fromMixed($validated['additional_params'][$field]);
            }
        }

        foreach (['additional_params', 'financial_company_merchant_info'] as $field) {
            if (isset($validated[$field]) && is_array($validated[$field])) {
                $validated[$field] = array_filter($validated[$field], static fn ($value) => $value !== null);
            }
            if (empty($validated[$field])) {
                unset($validated[$field]);
            }
        }
        if (($validated['result_callback'] ?? null) === null) {
            unset($validated['result_callback']);
        }
        if (($validated['invoice']['point_id'] ?? null) === null) {
            unset($validated['invoice']['point_id']);
        }

        return $validated;
    }

    /**
     * Endpoint path.
     */
    public function endpoint(): string
    {
        return '/api/order/create';
    }
}
