<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Support;

use Inkvizitoria\MonoParts\Contracts\SignerInterface;
use Inkvizitoria\MonoParts\Security\Signer;

/**
 * Backward-compatible HMAC signer wrapper.
 */
final class HmacSigner implements SignerInterface
{
    private readonly Signer $signer;

    /**
     * @param string $secret Shared secret provided by monobank for signing.
     * @param string $algo   Hash algorithm (default sha256).
     */
    public function __construct(string $secret, string $algo = 'sha256')
    {
        $this->signer = new Signer($secret, $algo);
    }

    public function sign(string $body): string
    {
        return $this->signer->sign($body);
    }

    public function verify(string $body, string $signature): bool
    {
        return $this->signer->verify($body, $signature);
    }

    public function assertValid(string $body, string $signature): void
    {
        $this->signer->assertValid($body, $signature);
    }
}
