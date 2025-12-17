<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Responses;

use Inkvizitoria\MonoParts\Enums\ReturnStatus;

/**
 * Response from returnOrder endpoint.
 */
final class ReturnResponse
{
    public function __construct(
        public readonly ReturnStatus $status,
        public readonly ?string $rawStatus = null,
    ) {
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public static function fromPayload(?array $payload): self
    {
        $status = is_array($payload) ? ($payload['status'] ?? null) : null;

        return new self(ReturnStatus::fromRaw(is_string($status) ? $status : null), is_string($status) ? $status : null);
    }
}
