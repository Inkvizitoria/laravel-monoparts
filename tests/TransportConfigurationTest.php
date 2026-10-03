<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inkvizitoria\MonoParts\Config\MonoPartsConfig;
use Inkvizitoria\MonoParts\Contracts\SignerInterface;
use Inkvizitoria\MonoParts\Exceptions\ConfigurationException;
use Inkvizitoria\MonoParts\Exceptions\TransportException;
use Inkvizitoria\MonoParts\Http\MonoPartsClient;
use Inkvizitoria\MonoParts\Security\Signer;
use Inkvizitoria\MonoParts\Support\SignatureFactory;

final class TransportConfigurationTest extends TestCase
{
    public function test_applies_configured_timeouts_and_does_not_retry(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store');
        $this->app['config']->set('monoparts.http', ['timeout' => 7, 'connect_timeout' => 3]);
        $attempts = 0;
        Http::fake(function ($request, $options) use (&$attempts) {
            ++$attempts;
            $this->assertSame(7, $options['timeout']);
            $this->assertSame(3, $options['connect_timeout']);
            throw new ConnectionException('timeout');
        });
        try {
            $this->app->make(MonoPartsClient::class)->checkPaid('123e4567-e89b-12d3-a456-426614174000');
            $this->fail('Network failure must be surfaced.');
        } catch (TransportException $e) {
            $this->assertInstanceOf(ConnectionException::class, $e->getPrevious());
            $this->assertSame(1, $attempts);
        }
    }

    public function test_invalid_environment_has_package_configuration_exception(): void
    {
        $this->expectException(ConfigurationException::class);
        MonoPartsConfig::fromArray(['environment' => 'typo']);
    }

    public function test_credentials_cannot_be_sent_over_plain_http(): void
    {
        $this->expectException(ConfigurationException::class);
        MonoPartsConfig::fromArray(['production_url' => 'http://example.test']);
    }

    public function test_invalid_timeout_is_rejected(): void
    {
        $this->expectException(ConfigurationException::class);
        MonoPartsConfig::fromArray(['production_url' => 'https://example.test', 'http' => ['timeout' => 0]]);
    }

    public function test_published_config_preserves_invalid_timeout_for_validation(): void
    {
        $repository = \Illuminate\Support\Env::getRepository();
        $repository->set('MONOPARTS_TIMEOUT', '1.9');
        try {
            $config = require __DIR__ . '/../config/monoparts.php';
            $this->expectException(ConfigurationException::class);
            MonoPartsConfig::fromArray($config);
        } finally {
            $repository->clear('MONOPARTS_TIMEOUT');
        }
    }

    public function test_invalid_signature_algorithm_fails_when_configured(): void
    {
        $this->expectException(ConfigurationException::class);
        new Signer('secret', 'invalid-algorithm');
    }

    public function test_unknown_signature_driver_does_not_resolve_default_binding_recursively(): void
    {
        $config = MonoPartsConfig::fromArray(['production_url' => 'https://example.test']);
        $this->expectException(\Inkvizitoria\MonoParts\Exceptions\ConfigurationException::class);
        // The provider has registered SignerInterface but it has not been resolved.
        $this->app->make(SignatureFactory::class)->make($config, ['driver' => 'invalid']);
    }
}
