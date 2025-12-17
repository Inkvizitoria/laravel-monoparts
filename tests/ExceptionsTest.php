<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Illuminate\Support\Facades\Validator;
use Inkvizitoria\MonoParts\Exceptions\ApiResponseException;
use Inkvizitoria\MonoParts\Exceptions\ConfigurationException;
use Inkvizitoria\MonoParts\Exceptions\PayloadValidationException;
use Inkvizitoria\MonoParts\Exceptions\SignatureValidationException;
use Inkvizitoria\MonoParts\Exceptions\TransportException;
use Inkvizitoria\MonoParts\Http\ExceptionResponse;

final class ExceptionsTest extends TestCase
{
    public function test_api_response_exception_uses_message(): void
    {
        $exceptionResponse = new ExceptionResponse('fail');
        $exception = new ApiResponseException(400, $exceptionResponse);

        $this->assertSame('fail', $exception->getMessage());
        $this->assertSame(400, $exception->statusCode);
    }

    public function test_payload_validation_exception_from_laravel(): void
    {
        try {
            Validator::make(['a' => null], ['a' => ['required']])->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $exception = PayloadValidationException::fromLaravel($e);
            $this->assertArrayHasKey('a', $exception->errors());
        }
    }

    public function test_other_exceptions_construct(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new SignatureValidationException('bad'));
        $this->assertInstanceOf(\RuntimeException::class, new ConfigurationException('bad'));
        $this->assertInstanceOf(\RuntimeException::class, new TransportException('bad'));
    }
}
