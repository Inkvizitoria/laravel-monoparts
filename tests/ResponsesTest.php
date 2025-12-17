<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Http\ExceptionResponse;
use Inkvizitoria\MonoParts\Http\Responses\CheckPaidResult;
use Inkvizitoria\MonoParts\Http\Responses\CreateOrderResult;
use Inkvizitoria\MonoParts\Http\Responses\DailyReport;
use Inkvizitoria\MonoParts\Http\Responses\DailyReportOrder;
use Inkvizitoria\MonoParts\Http\Responses\InstallmentAvailabilityResponse;
use Inkvizitoria\MonoParts\Http\Responses\OrderShortInfo;
use Inkvizitoria\MonoParts\Http\Responses\OrderStateInfo;
use Inkvizitoria\MonoParts\Http\Responses\ReverseEntry;
use Inkvizitoria\MonoParts\Http\Responses\ValidateClientResponse;
use Inkvizitoria\MonoParts\Enums\OrderState;

final class ResponsesTest extends TestCase
{
    public function test_simple_response_dtos(): void
    {
        $paid = CheckPaidResult::fromPayload([
            'fully_paid' => true,
            'bank_can_return_money_to_card' => false,
        ]);
        $this->assertTrue($paid->fullyPaid);
        $this->assertFalse($paid->bankCanReturnMoneyToCard);

        $order = CreateOrderResult::fromPayload(['order_id' => 'ORD-1']);
        $this->assertSame('ORD-1', $order->orderId);

        $availability = InstallmentAvailabilityResponse::fromPayload(['available' => true]);
        $this->assertTrue($availability->available);

        $validation = ValidateClientResponse::fromPayload(['found' => false]);
        $this->assertFalse($validation->found);
    }

    public function test_exception_response_from_payload(): void
    {
        $resp = ExceptionResponse::fromPayload(['message' => 'bad']);
        $this->assertSame('bad', $resp->message);
    }

    public function test_reverse_and_order_short_info(): void
    {
        $reverse = ReverseEntry::fromPayload([
            'sum' => 10.5,
            'timestamp' => '2021-06-16T16:49:51',
        ]);

        $this->assertSame(10.5, $reverse->sum);
        $this->assertNotNull($reverse->timestamp);

        $info = OrderShortInfo::fromPayload([
            'create_timestamp' => '2021-06-16T16:49:51',
            'iban' => 'UA123',
            'invoice_date' => '2021-01-01',
            'invoice_number' => 'INV',
            'maskedCard' => '****',
            'point_id' => '1',
            'reverse_list' => [
                ['sum' => 10.5, 'timestamp' => '2021-06-16T16:49:51'],
            ],
            'source' => 'INTERNET',
            'store_order_id' => 'SO-1',
            'total_sum' => 99.99,
        ]);

        $this->assertSame('UA123', $info->iban);
        $this->assertCount(1, $info->reverseList);
        $this->assertSame(99.99, $info->totalSum);
    }

    public function test_order_state_info_parsing(): void
    {
        $info = OrderStateInfo::fromPayload([
            'order_id' => 'ORD-1',
            'state' => 'SUCCESS',
            'order_sub_state' => 'SUCCESS',
            'message' => 'ok',
        ]);

        $this->assertSame('ORD-1', $info->orderId);
        $this->assertSame(OrderState::SUCCESS, $info->state);

        $unknown = OrderStateInfo::fromPayload([
            'state' => 'SOMETHING',
            'order_sub_state' => 'SOMETHING',
        ]);

        $this->assertNull($unknown->state);
        $this->assertSame('SOMETHING', $unknown->rawState);
        $this->assertSame('SOMETHING', $unknown->rawOrderSubState);
    }

    public function test_daily_report_mapping(): void
    {
        $order = DailyReportOrder::fromPayload([
            'card_number' => '1234',
            'commission' => 1.0,
            'commission_percent' => 2.0,
            'create_date_time' => '2021-06-16T16:49:51',
            'credit_sum' => 100.0,
            'invoice_number' => 'INV',
            'odb_contract_number' => 'C',
            'operation_timestamp' => '2021-06-16T16:49:51',
            'order_date' => '2021-01-01',
            'order_id' => 'ORD-1',
            'pay_parts' => 3,
            'payment_date' => '2021-01-02',
            'sent_sum' => 10.0,
            'terminal_id' => 'TERM',
            'total_sum' => 111.0,
            'transaction_date' => '2021-01-03',
            'transaction_id' => 'TX',
            'transferred_sum' => 9.0,
        ]);

        $this->assertSame('ORD-1', $order->orderId);

        $report = DailyReport::fromPayload(['orders' => [
            [
                'order_id' => 'ORD-1',
            ],
        ]]);

        $this->assertCount(1, $report->orders);
    }
}
