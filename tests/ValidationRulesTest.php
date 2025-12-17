<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Http\Requests\BrokerAvailabilityRequest;
use Inkvizitoria\MonoParts\Http\Requests\ClientValidateV2Request;
use Inkvizitoria\MonoParts\Http\Requests\CreateOrderRequest;
use Inkvizitoria\MonoParts\Http\Requests\ReturnOrderRequest;
use Inkvizitoria\MonoParts\Http\Requests\StoreReportRequest;
use Inkvizitoria\MonoParts\Http\Requests\OrderStateRequest;
use Illuminate\Validation\ValidationException;

final class ValidationRulesTest extends TestCase
{
    public function test_order_id_regex_is_enforced(): void
    {
        $request = new OrderStateRequest('not-valid-uuid');

        $this->expectException(ValidationException::class);
        $request->validate($request->payload());
    }

    public function test_order_id_validates(): void
    {
        $request = new OrderStateRequest('123e4567-e89b-12d3-a456-426614174000');
        $validated = $request->validate($request->payload());

        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $validated['order_id']);
    }

    public function test_broker_phone_validation(): void
    {
        $request = new BrokerAvailabilityRequest(
            amount: 10.0,
            employeeId: 'emp',
            inn: '12345',
            outletId: 'outlet',
            phone: 'invalid'
        );

        $this->expectException(ValidationException::class);
        $request->validate($request->payload());
    }

    public function test_broker_request_allows_valid_payload(): void
    {
        $request = new BrokerAvailabilityRequest(
            amount: 10.0,
            employeeId: 'emp',
            inn: '12345',
            outletId: 'outlet',
            phone: '+380500000001',
            brokerId: 'broker-1'
        );

        $validated = $request->validate($request->payload());
        $this->assertSame('+380500000001', $validated['phone']);
        $this->assertTrue($request->requiresBrokerId());
        $this->assertFalse($request->requiresStoreId());
        $this->assertSame('broker-1', $request->brokerId());
    }

    public function test_return_order_requires_min_sum(): void
    {
        $request = new ReturnOrderRequest(
            orderId: '123e4567-e89b-12d3-a456-426614174000',
            sum: 0.0,
            returnMoneyToCard: true,
            storeReturnId: 'RET-1'
        );

        $this->expectException(ValidationException::class);
        $request->validate($request->payload());
    }

    public function test_store_report_requires_date_format(): void
    {
        $request = new StoreReportRequest('01-01-2024');

        $this->expectException(ValidationException::class);
        $request->validate($request->payload());
    }

    public function test_client_validate_v2_accepts_optional_phone(): void
    {
        $request = new ClientValidateV2Request(null);
        $validated = $request->validate($request->payload());

        $this->assertSame([], $validated);
    }

    public function test_client_validate_v2_with_phone(): void
    {
        $request = new ClientValidateV2Request('+380500000001');
        $validated = $request->validate($request->payload());

        $this->assertSame(['phone' => '+380500000001'], $validated);
    }

    public function test_create_order_products_and_programs_are_required(): void
    {
        $request = new CreateOrderRequest([
            'store_order_id' => 'ORD-1',
            'client_phone' => '+380501234567',
            'total_sum' => 100,
            'invoice' => ['date' => '2024-01-01', 'number' => '1', 'source' => 'INTERNET'],
        ]);

        $this->expectException(ValidationException::class);
        $request->validate($request->payload());
    }

    public function test_create_order_valid_payload_passes(): void
    {
        $request = new CreateOrderRequest([
            'store_order_id' => 'ORD-1',
            'client_phone' => '+380501234567',
            'total_sum' => 100.25,
            'invoice' => ['date' => '2024-01-01', 'number' => '1', 'source' => 'INTERNET'],
            'available_programs' => [
                ['available_parts_count' => [3], 'type' => 'payment_installments'],
            ],
            'products' => [
                ['name' => 'Test', 'count' => 1, 'sum' => 100.25],
            ],
            'financial_company_merchant_info' => [
                'edrpou_code' => '12345678',
                'iban_account' => 'UA123456789012345678901234567',
                'store_name' => 'Shop',
            ],
            'additional_params' => [
                'nds' => 1.0,
                'seller_phone' => '+380501234567',
                'ext_initial_sum' => 10.0,
            ],
        ]);

        $validated = $request->validate($request->payload());
        $this->assertSame('ORD-1', $validated['store_order_id']);
    }

    public function test_return_order_additional_params_in_payload(): void
    {
        $request = new ReturnOrderRequest(
            orderId: '123e4567-e89b-12d3-a456-426614174000',
            sum: 10.0,
            returnMoneyToCard: true,
            storeReturnId: 'RET-1',
            additionalParams: ['nds' => 1.5]
        );

        $payload = $request->payload();
        $this->assertArrayHasKey('additional_params', $payload);
        $this->assertSame(['nds' => 1.5], $payload['additional_params']);
    }
}
