<?php

declare(strict_types=1);

use Inkvizitoria\MonoParts\Enums\Environment;

return [
    /*
    |--------------------------------------------------------------------------
    | Monobank Purchase-In-Parts Environment
    |--------------------------------------------------------------------------
    |
    | sandbox / stage are static and bundled as enum options.
    | production base URL is configurable (docs recommend using the official API host).
    |
    */
    'environment' => env('MONOPARTS_ENV', Environment::PRODUCTION->value),

    'base_urls' => [
        Environment::SANDBOX->value => 'https://u2-demo-ext.mono.st4g3.com',
        Environment::STAGE->value => 'https://u2-ext.mono.st4g3.com',
    ],

    'production_url' => env('MONOPARTS_PROD_URL', 'https://u2.monobank.com.ua'),

    /*
    |--------------------------------------------------------------------------
    | Merchant credentials
    |--------------------------------------------------------------------------
    |
    | store_id identifies the merchant, while signature_secret is used
    | by the default signer (HMAC). If you want to plug a custom signer, bind
    | the SignerInterface in a service provider.
    |
    */
    'merchant' => [
        'store_id' => env('MONOPARTS_STORE_ID', ''),
        'signature_secret' => env('MONOPARTS_SIGNATURE_SECRET', ''),
        'broker_id' => env('MONOPARTS_BROKER_ID', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature settings
    |--------------------------------------------------------------------------
    */
    'signature' => [
        'driver' => env('MONOPARTS_SIGNATURE_DRIVER', 'hmac'),
        'algo' => env('MONOPARTS_SIGNATURE_ALGO', 'sha256'),
        'header' => env('MONOPARTS_SIGNATURE_HEADER', 'signature'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'store' => env('MONOPARTS_STORE_ID_HEADER', 'store-id'),
        'broker' => env('MONOPARTS_BROKER_ID_HEADER', 'broker-id'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback / webhook handling
    |--------------------------------------------------------------------------
    */
    'callbacks' => [
        'enabled' => true,
        'path' => env('MONOPARTS_CALLBACK_PATH', '/monoparts/callback'),
        'middleware' => ['api'],
        'event' => \Inkvizitoria\MonoParts\Events\CallbackReceived::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel' => env('MONOPARTS_LOG_CHANNEL', 'monoparts'),
        'fallback_channel' => env('MONOPARTS_FALLBACK_CHANNEL', 'stack'),
        'channel_config' => [
            'driver' => 'single',
            'path' => storage_path('logs/monoparts.log'),
            'level' => env('MONOPARTS_LOG_LEVEL', 'info'),
        ],
    ],
];
