<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Config\MonoPartsConfig;
use Inkvizitoria\MonoParts\Enums\Environment;

final class ConfigTest extends TestCase
{
    public function test_resolves_environment_base_url(): void
    {
        $config = MonoPartsConfig::fromArray($this->app['config']->get('monoparts'));

        $this->assertSame(Environment::PRODUCTION, $config->environment);
        $this->assertSame('https://example.test', $config->baseUrl);
        $this->assertSame('signature', $config->signatureHeader);
        $this->assertSame('store-id', $config->storeHeader);
    }

    public function test_resolves_non_production_url(): void
    {
        $config = MonoPartsConfig::fromArray([
            'environment' => Environment::SANDBOX->value,
            'base_urls' => [
                Environment::SANDBOX->value => 'https://sandbox.test',
            ],
            'production_url' => 'https://prod.test',
            'merchant' => [
                'store_id' => 's',
                'signature_secret' => 'secret',
            ],
            'signature' => [
                'header' => 'signature',
            ],
            'headers' => [
                'store' => 'store-id',
                'broker' => 'broker-id',
            ],
        ]);

        $this->assertSame(Environment::SANDBOX, $config->environment);
        $this->assertSame('https://sandbox.test', $config->baseUrl);
    }

    public function test_throws_when_base_url_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MonoPartsConfig::fromArray([
            'environment' => Environment::STAGE->value,
            'base_urls' => [],
            'merchant' => [
                'store_id' => 's',
                'signature_secret' => 'secret',
            ],
            'signature' => [
                'header' => 'signature',
            ],
            'headers' => [
                'store' => 'store-id',
                'broker' => 'broker-id',
            ],
        ]);
    }
}
