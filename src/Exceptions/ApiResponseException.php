<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Exceptions;

use Inkvizitoria\MonoParts\Http\ExceptionResponse;

/**
 * Represents an error response returned by Monobank API.
 */
final class ApiResponseException extends MonoPartsException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly ExceptionResponse $exceptionResponse,
    ) {
        parent::__construct($exceptionResponse->message ?? 'Monobank API returned an error.');
    }
}
