<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Config\MonoPartsConfig;
use Inkvizitoria\MonoParts\Enums\Environment;
use Inkvizitoria\MonoParts\Support\MonoPartsLogger;
use Inkvizitoria\MonoParts\Support\SignatureFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class SupportTest extends TestCase
{
    public function test_signature_factory_returns_hmac_signer(): void
    {
        $config = new MonoPartsConfig(
            environment: Environment::PRODUCTION,
            baseUrl: 'https://example.test',
            storeId: 's',
            signatureSecret: 'secret',
            signatureHeader: 'signature',
            storeHeader: 'store-id',
            brokerId: null,
        );

        $factory = new SignatureFactory($this->app);
        $signer = $factory->make($config, ['driver' => 'hmac', 'algo' => 'sha256']);

        $this->assertTrue($signer->verify(['foo' => 'bar'], $signer->sign(['foo' => 'bar'])));
    }

    public function test_signature_factory_throws_on_unknown_driver(): void
    {
        $config = new MonoPartsConfig(
            environment: Environment::PRODUCTION,
            baseUrl: 'https://example.test',
            storeId: 's',
            signatureSecret: 'secret',
            signatureHeader: 'signature',
            storeHeader: 'store-id',
            brokerId: null,
        );

        $factory = new SignatureFactory(new \Illuminate\Container\Container());

        $this->expectException(\InvalidArgumentException::class);
        $factory->make($config, ['driver' => 'unknown']);
    }

    public function test_signature_factory_returns_bound_signer(): void
    {
        $config = new MonoPartsConfig(
            environment: Environment::PRODUCTION,
            baseUrl: 'https://example.test',
            storeId: 's',
            signatureSecret: 'secret',
            signatureHeader: 'signature',
            storeHeader: 'store-id',
            brokerId: null,
        );

        $container = new \Illuminate\Container\Container();
        $dummySigner = new class implements \Inkvizitoria\MonoParts\Contracts\SignerInterface {
            public function sign(array $payload): string
            {
                return 'dummy';
            }

            public function verify(array $payload, string $signature): bool
            {
                return $signature === 'dummy';
            }
        };
        $container->instance(\Inkvizitoria\MonoParts\Contracts\SignerInterface::class, $dummySigner);

        $factory = new SignatureFactory($container);
        $signer = $factory->make($config, ['driver' => 'custom']);

        $this->assertSame($dummySigner, $signer);
    }

    public function test_logger_resolves_channel(): void
    {
        $logger = new MonoPartsLogger($this->app->make('log'), [
            'channel' => null,
            'fallback_channel' => 'stack',
        ]);

        $this->assertInstanceOf(LoggerInterface::class, $logger->get());
    }

    public function test_logger_builds_custom_channel_when_channel_fails(): void
    {
        $logManager = $this->createMock(\Illuminate\Log\LogManager::class);
        $logManager->method('channel')->willThrowException(new \RuntimeException('fail'));
        $logManager->method('build')->willReturn(new NullLogger());

        $logger = new MonoPartsLogger($logManager, [
            'channel' => 'custom',
            'channel_config' => ['driver' => 'single'],
            'fallback_channel' => 'stack',
        ]);

        $this->assertInstanceOf(NullLogger::class, $logger->get());
    }

    public function test_logger_falls_back_to_null_logger_on_failure(): void
    {
        $logManager = $this->createMock(\Illuminate\Log\LogManager::class);
        $logManager->method('channel')->willThrowException(new \RuntimeException('fail'));
        $logManager->method('build')->willThrowException(new \RuntimeException('fail'));

        $logger = new MonoPartsLogger($logManager, [
            'channel' => 'custom',
            'channel_config' => ['driver' => 'single'],
            'fallback_channel' => 'stack',
        ]);

        $this->assertInstanceOf(NullLogger::class, $logger->get());
    }
}
