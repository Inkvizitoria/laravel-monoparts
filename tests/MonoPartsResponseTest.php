<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Http\MonoPartsResponse;
use Inkvizitoria\MonoParts\Status\ResponseStatus;
use Illuminate\Support\Facades\Http;

final class MonoPartsResponseTest extends TestCase
{
    public function test_successful_matches_business_rules(): void
    {
        $ok = new MonoPartsResponse(ResponseStatus::ORDER_SUCCESS, 200, ['foo' => 'bar']);
        $this->assertTrue($ok->successful());

        $dup = new MonoPartsResponse(ResponseStatus::ORDER_DUPLICATE, 409, null);
        $this->assertTrue($dup->successful());

        $fail = new MonoPartsResponse(ResponseStatus::ORDER_FAIL, 200, null);
        $this->assertFalse($fail->successful());
    }

    public function test_from_http_builds_response(): void
    {
        $response = new \Illuminate\Http\Client\Response(
            new \GuzzleHttp\Psr7\Response(200, ['X-Test' => '1'], json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR))
        );
        $mono = MonoPartsResponse::fromHttp($response, ResponseStatus::SUCCESS_HTTP, ['data' => 1]);

        $this->assertSame(ResponseStatus::SUCCESS_HTTP, $mono->status);
        $this->assertSame(200, $mono->httpStatus);
        $this->assertSame(['foo' => 'bar'], $mono->raw);
        $this->assertSame(['data' => 1], $mono->data);
    }
}
