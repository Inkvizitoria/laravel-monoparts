<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Exceptions;

use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Validation\ValidationException as IlluminateValidationException;
use Inkvizitoria\MonoParts\Exceptions\MonoPartsException;

/**
 * Thrown when outgoing payload validation fails before calling Monobank API.
 */
final class PayloadValidationException extends MonoPartsException
{
    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Payload validation failed.'
    ) {
        parent::__construct($message);
    }

    /**
     * Build from native Laravel ValidationException for convenience.
     */
    public static function fromLaravel(IlluminateValidationException $e): self
    {
        return new self($e->errors(), $e->getMessage());
    }

    /**
     * Validation errors array grouped by field.
     *
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
