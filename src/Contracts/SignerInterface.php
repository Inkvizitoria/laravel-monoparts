<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Contracts;

interface SignerInterface
{
    /**
     * Create signature for a request/response payload.
     *
     * @param array<string, mixed> $payload
     */
    public function sign(array $payload): string;

    /**
     * Verify payload signature.
     *
     * @param array<string, mixed> $payload
     */
    public function verify(array $payload, string $signature): bool;
}
