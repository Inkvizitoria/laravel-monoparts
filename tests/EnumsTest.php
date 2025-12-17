<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Enums\OrderSubState;
use Inkvizitoria\MonoParts\Enums\ReturnStatus;

final class EnumsTest extends TestCase
{
    public function test_order_sub_state_try_from_string(): void
    {
        $this->assertNull(OrderSubState::tryFromString(null));
        $this->assertNull(OrderSubState::tryFromString('UNKNOWN'));
        $this->assertSame(OrderSubState::SUCCESS, OrderSubState::tryFromString('SUCCESS'));
    }

    public function test_return_status_from_raw(): void
    {
        $this->assertSame(ReturnStatus::ERROR, ReturnStatus::fromRaw(null));
        $this->assertSame(ReturnStatus::OK, ReturnStatus::fromRaw('OK'));
        $this->assertSame(ReturnStatus::ERROR, ReturnStatus::fromRaw('NOT_OK'));
    }
}
