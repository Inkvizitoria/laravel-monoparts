<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Providers\MonoPartsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public static function applicationBasePath(): string
    {
        return realpath(__DIR__ . '/workbench') ?: __DIR__ . '/workbench';
    }

    protected function getPackageProviders($app): array
    {
        return [
            MonoPartsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('monoparts.production_url', 'https://example.test');
        $app['config']->set('monoparts.base_urls', [
            'sandbox' => 'https://sandbox.test',
            'stage' => 'https://stage.test',
        ]);
        $app['config']->set('monoparts.callbacks.middleware', []);
    }
}
