<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Responses;

/**
 * Broker availability result.
 */
final class InstallmentAvailabilityResponse
{
    public function __construct(
        public readonly bool $available,
    ) {
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public static function fromPayload(?array $payload): self
    {
        return new self((bool) ($payload['available'] ?? false));
    }
}
