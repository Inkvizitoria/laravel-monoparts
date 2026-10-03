<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Config\MonoPartsConfig;
use Inkvizitoria\MonoParts\Contracts\SignerInterface;
use Inkvizitoria\MonoParts\Enums\Environment;
use Inkvizitoria\MonoParts\Http\MonoPartsClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

#[\PHPUnit\Framework\Attributes\Group('integration')]
final class MonobankIntegrationTest extends TestCase
{
    private const SANDBOX_BASE_URL = 'https://u2-demo-ext.mono.st4g3.com';
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MONOPARTS_RUN_INTEGRATION') !== '1') {
            $this->markTestSkipped('Set MONOPARTS_RUN_INTEGRATION=1 to enable sandbox requests.');
        }
        foreach (['MONOPARTS_TEST_STORE_ID', 'MONOPARTS_TEST_SIGNATURE_SECRET', 'MONOPARTS_TEST_PHONE'] as $key) {
            if (!getenv($key)) {
                $this->markTestSkipped($key . ' is required for sandbox integration tests.');
            }
        }
    }

    public function test_validate_client_v2_calls_monobank(): void
    {
        $client = $this->makeClient();

        $response = $client->validateClientV2((string) getenv('MONOPARTS_TEST_PHONE'));

        $this->assertIsBool($response->found);
    }

    public function test_create_order_and_order_state_calls_monobank(): void
    {
        if (getenv('MONOPARTS_TEST_CREATE_ORDER') !== '1') {
            $this->markTestSkipped('Set MONOPARTS_TEST_CREATE_ORDER=1 to create a sandbox order.');
        }
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
            'client_phone' => (string) getenv('MONOPARTS_TEST_PHONE'),
            'total_sum' => '100.00',
            'invoice' => [
                'date' => date('Y-m-d'),
                'number' => $uniqueId,
                'source' => 'INTERNET',
            ],
            'available_programs' => [
                ['available_parts_count' => [3], 'type' => 'payment_installments'],
            ],
            'products' => [
                ['name' => 'Test product', 'count' => 1, 'sum' => '100.00'],
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
        $this->app['config']->set('monoparts.merchant.store_id', (string) getenv('MONOPARTS_TEST_STORE_ID'));
        $this->app['config']->set('monoparts.merchant.signature_secret', (string) getenv('MONOPARTS_TEST_SIGNATURE_SECRET'));
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
