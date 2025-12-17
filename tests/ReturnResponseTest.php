<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Http\Responses\ReturnResponse;
use Inkvizitoria\MonoParts\Enums\ReturnStatus;

final class ReturnResponseTest extends TestCase
{
    public function test_from_payload_handles_ok(): void
    {
        $resp = ReturnResponse::fromPayload(['status' => 'OK']);

        $this->assertSame(ReturnStatus::OK, $resp->status);
        $this->assertSame('OK', $resp->rawStatus);
    }

    public function test_from_payload_defaults_to_error(): void
    {
        $resp = ReturnResponse::fromPayload(['status' => 'SOMETHING_ELSE']);

        $this->assertSame(ReturnStatus::ERROR, $resp->status);
        $this->assertSame('SOMETHING_ELSE', $resp->rawStatus);
    }
}
