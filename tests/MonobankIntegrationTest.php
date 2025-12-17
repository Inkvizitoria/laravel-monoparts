<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Config\MonoPartsConfig;
use Inkvizitoria\MonoParts\Contracts\SignerInterface;
use Inkvizitoria\MonoParts\Enums\Environment;
use Inkvizitoria\MonoParts\Http\MonoPartsClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * @group integration
 */
final class MonobankIntegrationTest extends TestCase
{
    private const SANDBOX_BASE_URL = 'https://u2-demo-ext.mono.st4g3.com';
    private const STORE_ID = 'test_store_with_confirm';
    private const SIGNATURE_SECRET = 'secret_98765432--123-123';
    private const TEST_PHONE = '+380931234561';

    public function test_validate_client_v2_calls_monobank(): void
    {
        $client = $this->makeClient();

        $response = $client->validateClientV2(self::TEST_PHONE);

        $this->assertIsBool($response->found);
    }

    public function test_create_order_and_order_state_calls_monobank(): void
    {
        $client = $this->makeClient();
        $payload = $this->buildCreatePayload();

        $createResponse = $client->createOrder($payload);
        $this->assertNotSame('', $createResponse->orderId);

        $stateResponse = $client->orderState($createResponse->orderId);
        $this->assertNotNull($stateResponse->orderId);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreatePayload(): array
    {
        $uniqueId = 'TEST-' . str_replace('.', '', (string) microtime(true));

        return [
            'store_order_id' => $uniqueId,
            'client_phone' => self::TEST_PHONE,
            'total_sum' => 100.00,
            'invoice' => [
                'date' => date('Y-m-d'),
                'number' => $uniqueId,
                'source' => 'INTERNET',
            ],
            'available_programs' => [
                ['available_parts_count' => [3], 'type' => 'payment_installments'],
            ],
            'products' => [
                ['name' => 'Test product', 'count' => 1, 'sum' => 100.00],
            ],
        ];
    }

    private function makeClient(): MonoPartsClient
    {
        $this->app['config']->set('monoparts.environment', Environment::SANDBOX->value);
        $this->app['config']->set('monoparts.base_urls', [
            Environment::SANDBOX->value => self::SANDBOX_BASE_URL,
            Environment::STAGE->value => 'https://u2-ext.mono.st4g3.com',
        ]);
        $this->app['config']->set('monoparts.production_url', 'https://u2.monobank.com.ua');
        $this->app['config']->set('monoparts.merchant.store_id', self::STORE_ID);
        $this->app['config']->set('monoparts.merchant.signature_secret', self::SIGNATURE_SECRET);
        $this->app['config']->set('monoparts.signature.header', 'signature');

        $factory = new Factory();
        $this->app->instance('http', $factory);
        Http::swap($factory);

        $this->app->forgetInstance(MonoPartsConfig::class);
        $this->app->forgetInstance(SignerInterface::class);
        $this->app->forgetInstance(MonoPartsClient::class);

        return $this->app->make(MonoPartsClient::class);
    }
}
