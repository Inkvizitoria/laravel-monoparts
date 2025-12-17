<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Http\Requests\ReturnOrderRequest;
use Inkvizitoria\MonoParts\Http\Requests\StoreReportRequest;
use Inkvizitoria\MonoParts\Http\Requests\MonoPartsRequest;
use Illuminate\Validation\ValidationException;

final class RequestsTest extends TestCase
{
    public function test_return_order_payload_contains_required_fields(): void
    {
        $request = new ReturnOrderRequest(
            orderId: '123e4567-e89b-12d3-a456-426614174000',
            sum: 10.25,
            returnMoneyToCard: true,
            storeReturnId: 'RET-1'
        );

        $payload = $request->payload();
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $payload['order_id']);
        $this->assertSame(10.25, $payload['sum']);
        $this->assertTrue($payload['return_money_to_card']);
        $this->assertSame('RET-1', $payload['store_return_id']);
    }

    public function test_store_report_rules_accept_valid_date(): void
    {
        $request = new StoreReportRequest('2024-01-01');
        $validated = $request->validate($request->payload());

        $this->assertSame(['date' => '2024-01-01'], $validated);
    }

    public function test_store_report_rules_reject_invalid_date(): void
    {
        $request = new StoreReportRequest('2024/01/01');

        $this->expectException(ValidationException::class);
        $request->validate($request->payload());
    }

    public function test_base_request_defaults(): void
    {
        $request = new class extends MonoPartsRequest {
            public function payload(): array
            {
                return ['foo' => 'bar'];
            }

            public function rules(): array
            {
                return ['foo' => ['required']];
            }

            public function endpoint(): string
            {
                return '/dummy';
            }
        };

        $this->assertSame([], $request->headers());
        $this->assertTrue($request->requiresStoreId());
        $this->assertFalse($request->requiresBrokerId());
        $this->assertNull($request->brokerId());

        $this->assertSame(['foo' => 'bar'], $request->validate($request->payload()));
    }

    public function test_validate_returns_payload_when_rules_are_empty(): void
    {
        $request = new class extends MonoPartsRequest {
            public function payload(): array
            {
                return ['foo' => 'bar'];
            }

            public function endpoint(): string
            {
                return '/dummy';
            }
        };

        $payload = ['foo' => 'bar'];
        $this->assertSame($payload, $request->validate($payload));
    }
}
