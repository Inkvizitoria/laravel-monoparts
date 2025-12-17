<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Providers;

use Inkvizitoria\MonoParts\Config\MonoPartsConfig;
use Inkvizitoria\MonoParts\Contracts\CallbackHandlerInterface;
use Inkvizitoria\MonoParts\Contracts\SignerInterface;
use Inkvizitoria\MonoParts\Http\Callbacks\CallbackProcessor;
use Inkvizitoria\MonoParts\Http\Controllers\CallbackController;
use Inkvizitoria\MonoParts\Http\MonoPartsClient;
use Inkvizitoria\MonoParts\Support\MonoPartsLogger;
use Inkvizitoria\MonoParts\Support\SignatureFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class MonoPartsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/monoparts.php', 'monoparts');
        $this->registerLoggingChannel();

        $this->app->singleton(MonoPartsConfig::class, static function (Container $app): MonoPartsConfig {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('monoparts', []);

            return MonoPartsConfig::fromArray($config);
        });

        $this->app->singleton(SignatureFactory::class);

        $this->app->singleton(SignerInterface::class, function (Container $app): SignerInterface {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('monoparts', []);

            return $app->make(SignatureFactory::class)->make(
                $app->make(MonoPartsConfig::class),
                $config['signature'] ?? []
            );
        });

        $this->app->singleton(MonoPartsLogger::class, static function (Container $app): MonoPartsLogger {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('monoparts.logging', []);

            return new MonoPartsLogger($app->make('log'), $config);
        });

        $this->app->singleton(MonoPartsClient::class, static function (Container $app): MonoPartsClient {
            $httpClient = $app->bound('http') ? $app->make('http') : (Http::getFacadeRoot() ?? new Factory());

            return new MonoPartsClient(
                http: $httpClient,
                config: $app->make(MonoPartsConfig::class),
                signer: $app->make(SignerInterface::class),
                events: $app->make('events'),
                logger: $app->make(MonoPartsLogger::class),
            );
        });

        $this->app->singleton(CallbackProcessor::class, static function (Container $app): CallbackProcessor {
            return new CallbackProcessor(
                signer: $app->make(SignerInterface::class),
                config: $app->make(MonoPartsConfig::class),
                events: $app->make('events'),
                logger: $app->make(MonoPartsLogger::class),
            );
        });

        $this->app->alias(CallbackProcessor::class, CallbackHandlerInterface::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/monoparts.php' => config_path('monoparts.php'),
        ], 'config');

        $this->registerRoutes();
    }

    /**
     * Register callback route when enabled.
     */
    private function registerRoutes(): void
    {
        /** @var array<string, mixed> $config */
        $config = $this->app['config']->get('monoparts.callbacks', []);

        if (!($config['enabled'] ?? true)) {
            return;
        }

        $path = $config['path'] ?? '/monoparts/callback';
        $middleware = $config['middleware'] ?? ['api'];

        Route::middleware($middleware)
            ->post($path, CallbackController::class)
            ->name('monoparts.callback');
    }

    /**
     * Register the default log channel if it is missing from Laravel logging config.
     */
    private function registerLoggingChannel(): void
    {
        /** @var array<string, mixed> $logging */
        $logging = $this->app['config']->get('monoparts.logging', []);
        $channelName = is_string($logging['channel'] ?? null) ? trim((string) $logging['channel']) : '';
        $channelConfig = is_array($logging['channel_config'] ?? null) ? $logging['channel_config'] : [];
        $channels = $this->app['config']->get('logging.channels', []);
        $channels = is_array($channels) ? $channels : [];

        if ($channelName !== '' && $channelConfig !== [] && !array_key_exists($channelName, $channels)) {
            $this->app['config']->set("logging.channels.{$channelName}", $channelConfig);
        }
    }
}
