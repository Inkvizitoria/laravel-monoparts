<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Config;

use Inkvizitoria\MonoParts\Enums\Environment;
use InvalidArgumentException;

final class MonoPartsConfig
{
    public function __construct(
        public readonly Environment $environment,
        public readonly string $baseUrl,
        public readonly ?string $storeId,
        public readonly ?string $signatureSecret,
        public readonly string $signatureHeader,
        public readonly string $storeHeader,
        public readonly ?string $brokerId,
        /** @var array<string, string> */
        public readonly array $headers = [],
    ) {
    }

    /**
     * Build from array config (supports config/monoparts.php shape).
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $env = Environment::from($config['environment'] ?? Environment::PRODUCTION->value);
        $baseUrls = $config['base_urls'] ?? [];
        $baseUrl = $config['production_url'] ?? '';

        if ($env !== Environment::PRODUCTION) {
            $baseUrl = $baseUrls[$env->value] ?? null;
        }

        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new InvalidArgumentException('Base URL is not configured for selected environment.');
        }

        return new self(
            environment: $env,
            baseUrl: rtrim($baseUrl, '/'),
            storeId: $config['merchant']['store_id'] ?? null,
            signatureSecret: $config['merchant']['signature_secret'] ?? null,
            signatureHeader: (string) ($config['signature']['header'] ?? 'signature'),
            storeHeader: (string) ($config['headers']['store'] ?? 'store-id'),
            brokerId: $config['merchant']['broker_id'] ?? null,
            headers: $config['headers'] ?? [],
        );
    }
}
