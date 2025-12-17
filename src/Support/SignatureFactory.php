<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Support;

use Inkvizitoria\MonoParts\Config\MonoPartsConfig;
use Inkvizitoria\MonoParts\Contracts\SignerInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class SignatureFactory
{
    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * Build signer implementation.
     *
     * @param array<string, mixed> $signatureConfig
     */
    public function make(MonoPartsConfig $config, array $signatureConfig): SignerInterface
    {
        $driver = $signatureConfig['driver'] ?? 'hmac';

        if ($driver === 'hmac') {
            $algo = (string) ($signatureConfig['algo'] ?? 'sha256');

            return new HmacSigner($config->signatureSecret ?? '', $algo);
        }

        if ($this->container->bound(SignerInterface::class)) {
            return $this->container->make(SignerInterface::class);
        }

        throw new InvalidArgumentException(sprintf('Unsupported signature driver [%s]', (string) $driver));
    }
}
