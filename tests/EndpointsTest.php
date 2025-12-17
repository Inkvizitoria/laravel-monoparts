<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Http\Requests\CheckPaidRequest;
use Inkvizitoria\MonoParts\Http\Requests\ConfirmOrderRequest;
use Inkvizitoria\MonoParts\Http\Requests\OrderDataRequest;
use Inkvizitoria\MonoParts\Http\Requests\OrderStateRequest;
use Inkvizitoria\MonoParts\Http\Requests\RejectOrderRequest;
use Inkvizitoria\MonoParts\Http\Requests\StoreReportRequest;

final class EndpointsTest extends TestCase
{
    public function test_endpoint_paths(): void
    {
        $this->assertSame('/api/order/check/paid', (new CheckPaidRequest('123e4567-e89b-12d3-a456-426614174000'))->endpoint());
        $this->assertSame('/api/order/confirm', (new ConfirmOrderRequest('123e4567-e89b-12d3-a456-426614174000'))->endpoint());
        $this->assertSame('/api/order/data', (new OrderDataRequest('123e4567-e89b-12d3-a456-426614174000'))->endpoint());
        $this->assertSame('/api/order/state', (new OrderStateRequest('123e4567-e89b-12d3-a456-426614174000'))->endpoint());
        $this->assertSame('/api/order/reject', (new RejectOrderRequest('123e4567-e89b-12d3-a456-426614174000'))->endpoint());
        $this->assertSame('/api/store/report', (new StoreReportRequest('2024-01-01'))->endpoint());
    }
}
