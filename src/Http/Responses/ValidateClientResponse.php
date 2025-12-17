<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Responses;

/**
 * Response from /api/v2/client/validate.
 */
final class ValidateClientResponse
{
    public function __construct(
        public readonly bool $found,
    ) {
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public static function fromPayload(?array $payload): self
    {
        return new self((bool) ($payload['found'] ?? false));
    }
}
