<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http;

/**
 * Canonical representation of error responses from Monobank API.
 */
final class ExceptionResponse
{
    public function __construct(
        public readonly ?string $message,
    ) {
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public static function fromPayload(?array $payload): self
    {
        return new self($payload['message'] ?? null);
    }
}
