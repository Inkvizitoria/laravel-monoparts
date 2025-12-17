<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

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
            'total_sum' => ['required', 'numeric', 'min:1', 'regex:/^\\d+(\\.\\d{1,2})?$/'],
            'invoice' => ['required', 'array'],
            'invoice.date' => ['required', 'date_format:Y-m-d'],
            'invoice.number' => ['required', 'string', 'min:1'],
            'invoice.point_id' => ['nullable', 'string', 'min:1', 'max:50'],
            'invoice.source' => ['required', 'in:STORE,INTERNET,CHECKOUT'],
            'available_programs' => ['required', 'array', 'min:1'],
            'available_programs.*.available_parts_count' => ['required', 'array', 'min:1'],
            'available_programs.*.available_parts_count.*' => ['integer', 'min:1'],
            'available_programs.*.type' => ['required', 'string', 'regex:/^payment_installments$/'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.name' => ['required', 'string', 'min:1', 'max:500'],
            'products.*.count' => ['required', 'integer', 'min:1'],
            'products.*.sum' => ['required', 'numeric', 'min:0.01', 'regex:/^\\d+(\\.\\d{1,2})?$/'],
            'result_callback' => ['nullable', 'url'],
            'financial_company_merchant_info' => ['nullable', 'array'],
            'financial_company_merchant_info.edrpou_code' => ['nullable', 'string', 'regex:/^\\d+$/'],
            'financial_company_merchant_info.iban_account' => ['nullable', 'string', 'regex:/^UA\\d{27}$/'],
            'financial_company_merchant_info.store_name' => ['nullable', 'string'],
            'additional_params' => ['nullable', 'array'],
            'additional_params.nds' => ['nullable', 'numeric'],
            'additional_params.seller_phone' => ['nullable', 'string', 'regex:/^\\+380\\d{9}$/'],
            'additional_params.ext_initial_sum' => ['nullable', 'numeric'],
        ];
    }

    /**
     * Endpoint path.
     */
    public function endpoint(): string
    {
        return '/api/order/create';
    }
}
