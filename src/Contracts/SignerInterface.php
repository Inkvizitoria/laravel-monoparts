<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Contracts;

interface SignerInterface
{
    /**
     * Create signature for a raw body string.
     */
    public function sign(string $body): string;

    /**
     * Verify signature for a raw body string.
     */
    public function verify(string $body, string $signature): bool;

    /**
     * Assert that signature is valid for a raw body string.
     */
    public function assertValid(string $body, string $signature): void;
}
