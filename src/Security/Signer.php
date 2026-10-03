<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Security;

use Inkvizitoria\MonoParts\Contracts\SignerInterface;
use Inkvizitoria\MonoParts\Exceptions\InvalidSignatureException;
use Inkvizitoria\MonoParts\Exceptions\ConfigurationException;

/**
 * HMAC SHA256 signer for raw JSON bodies.
 */
class Signer implements SignerInterface
{
    /**
     * @param string $secret Shared secret provided by Monobank.
     * @param string $algo   Hash algorithm (default sha256).
     */
    public function __construct(
        private readonly string $secret,
        private readonly string $algo = 'sha256',
    ) {
        if (!in_array($algo, hash_hmac_algos(), true)) {
            throw new ConfigurationException('Unsupported HMAC algorithm.');
        }
        if ($secret === '') {
            throw new ConfigurationException('Signature secret is not configured.');
        }
    }

    public function sign(string $body): string
    {
        return base64_encode(hash_hmac($this->algo, $body, $this->secret, true));
    }

    public function verify(string $body, string $signature): bool
    {
        return hash_equals($this->sign($body), $signature);
    }

    public function assertValid(string $body, string $signature): void
    {
        if (!$this->verify($body, $signature)) {
            throw new InvalidSignatureException('Invalid signature.');
        }
    }
}
