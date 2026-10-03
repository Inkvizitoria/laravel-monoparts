<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Support;

use Inkvizitoria\MonoParts\Config\MonoPartsConfig;
use Inkvizitoria\MonoParts\Contracts\SignerInterface;
use Inkvizitoria\MonoParts\Security\Signer;
use Illuminate\Contracts\Container\Container;
use Inkvizitoria\MonoParts\Exceptions\ConfigurationException;

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

            return new Signer($config->signatureSecret ?? '', $algo);
        }

        if ($this->container->resolved(SignerInterface::class)) {
            return $this->container->make(SignerInterface::class);
        }

        throw new ConfigurationException(sprintf('Unsupported signature driver [%s]', (string) $driver));
    }
}
