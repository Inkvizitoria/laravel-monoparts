<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Support;

use Inkvizitoria\MonoParts\Contracts\SignerInterface;
use RuntimeException;

final class HmacSigner implements SignerInterface
{
    /**
     * @param string $secret Shared secret provided by monobank for signing.
     * @param string $algo   Hash algorithm (default sha256).
     */
    public function __construct(
        private readonly string $secret,
        private readonly string $algo = 'sha256',
    ) {
        if ($secret === '') {
            throw new RuntimeException('Signature secret is not configured.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function sign(array $payload): string
    {
        $encoded = $this->encode($payload);

        return base64_encode(hash_hmac($this->algo, $encoded, $this->secret, true));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function verify(array $payload, string $signature): bool
    {
        $expected = $this->sign($payload);

        return hash_equals($expected, $signature);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
