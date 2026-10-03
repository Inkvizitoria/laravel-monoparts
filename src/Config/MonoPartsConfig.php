<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Config;

use Inkvizitoria\MonoParts\Enums\Environment;
use Inkvizitoria\MonoParts\Exceptions\ConfigurationException;

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
        public readonly bool $verifyResponseSignature,
        /** @var array<string, string> */
        public readonly array $headers = [],
        public readonly int $timeout = 30,
        public readonly int $connectTimeout = 10,
    ) {
    }

    /**
     * Build from array config (supports config/monoparts.php shape).
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $environment = $config['environment'] ?? Environment::PRODUCTION->value;
        $env = is_string($environment) ? Environment::tryFrom($environment) : null;
        if ($env === null) {
            throw new ConfigurationException('environment must be sandbox, stage or production.');
        }
        $baseUrls = $config['base_urls'] ?? [];
        $baseUrl = $config['production_url'] ?? '';

        if ($env !== Environment::PRODUCTION) {
            $baseUrl = $baseUrls[$env->value] ?? null;
        }

        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new ConfigurationException('Base URL is not configured for selected environment.');
        }

        $urlParts = parse_url($baseUrl);
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false || ($urlParts['scheme'] ?? '') !== 'https'
            || (isset($urlParts['user']) || isset($urlParts['pass'])) || isset($urlParts['query']) || isset($urlParts['fragment'])) {
            throw new ConfigurationException('Base URL must be an absolute HTTPS URL without credentials, query or fragment.');
        }
        $http = $config['http'] ?? [];
        $timeout = filter_var($http['timeout'] ?? 30, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $connectTimeout = filter_var($http['connect_timeout'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($timeout === false || $connectTimeout === false) {
            throw new ConfigurationException('HTTP timeouts must be positive integers in seconds.');
        }

        return new self(
            environment: $env,
            baseUrl: rtrim($baseUrl, '/'),
            storeId: $config['merchant']['store_id'] ?? null,
            signatureSecret: $config['merchant']['signature_secret'] ?? null,
            signatureHeader: (string) ($config['signature']['header'] ?? 'signature'),
            storeHeader: (string) ($config['headers']['store'] ?? 'store-id'),
            brokerId: $config['merchant']['broker_id'] ?? null,
            verifyResponseSignature: (bool) ($config['verify_response_signature'] ?? false),
            headers: $config['headers'] ?? [],
            timeout: $timeout,
            connectTimeout: $connectTimeout,
        );
    }
}
