<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Exceptions\ApiResponseException;
use Inkvizitoria\MonoParts\Exceptions\ConfigurationException;
use Inkvizitoria\MonoParts\Exceptions\PayloadValidationException;
use Inkvizitoria\MonoParts\Http\MonoPartsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

final class ClientTest extends TestCase
{
    public function test_signs_and_sends_request(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        Http::fake([
            'https://example.test/api/order/check/paid' => Http::response([
                'fully_paid' => true,
                'bank_can_return_money_to_card' => true,
            ]),
        ]);

        Event::fake();

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);
        $orderId = '123e4567-e89b-12d3-a456-426614174000';
        $response = $client->checkPaid($orderId);

        $this->assertTrue($response->fullyPaid);
        $this->assertTrue($response->bankCanReturnMoneyToCard);

        Http::assertSent(function (Request $request): bool {
            /** @var array<string, mixed> $body */
            $body = $request->data();
            $expectedSignature = base64_encode(hash_hmac('sha256', json_encode([
                'order_id' => '123e4567-e89b-12d3-a456-426614174000',
            ], JSON_THROW_ON_ERROR), 'secret', true));

            return $request->hasHeader('store-id', 'store-1')
                && $request->hasHeader('signature', $expectedSignature)
                && $body['order_id'] === '123e4567-e89b-12d3-a456-426614174000';
        });
    }

    public function test_validation_exception_is_thrown_for_invalid_payload(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);

        $this->expectException(PayloadValidationException::class);
        $client->createOrder([
            'client_phone' => '+380500000000',
        ]);
    }

    public function test_api_error_is_normalized(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        Http::fake([
            'https://example.test/api/order/check/paid' => Http::response(['message' => 'bad request'], 400),
        ]);

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);

        $this->expectException(ApiResponseException::class);
        $client->checkPaid('123e4567-e89b-12d3-a456-426614174000');
    }

    public function test_broker_request_skips_store_header(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.broker_id', 'broker-1');

        Http::fake([
            'https://example.test/api/fin/broker/check/installment/availability' => Http::response([
                'available' => true,
            ]),
        ]);

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);
        $response = $client->brokerAvailability(
            amount: 1000.00,
            employeeId: 'emp',
            inn: '1234567890',
            outletId: 'outlet',
            phone: '+380500000001'
        );

        $this->assertTrue($response->available);

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('broker-id', 'broker-1')
                && !$request->hasHeader('store-id');
        });
    }

    public function test_missing_store_id_throws_configuration_exception(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', '');

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);

        $this->expectException(ConfigurationException::class);
        $client->checkPaid('123e4567-e89b-12d3-a456-426614174000');
    }

    public function test_missing_broker_id_throws_configuration_exception(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.broker_id', '');

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);

        $this->expectException(ConfigurationException::class);
        $client->brokerAvailability(
            amount: 10.0,
            employeeId: 'emp',
            inn: '12345',
            outletId: 'outlet',
            phone: '+380500000001'
        );
    }

    public function test_check_paid_false_sets_status(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        Http::fake([
            'https://example.test/api/order/check/paid' => Http::response([
                'fully_paid' => false,
                'bank_can_return_money_to_card' => false,
            ]),
        ]);

        $capturedStatus = null;
        Event::listen(\Inkvizitoria\MonoParts\Events\ResponseReceived::class, function ($event) use (&$capturedStatus): void {
            $capturedStatus = $event->response->status;
        });

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);
        $client->checkPaid('123e4567-e89b-12d3-a456-426614174000');

        $this->assertSame(\Inkvizitoria\MonoParts\Status\ResponseStatus::CHECK_PAID_NO, $capturedStatus);
    }

    public function test_broker_availability_false_sets_status(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.broker_id', 'broker-1');

        Http::fake([
            'https://example.test/api/fin/broker/check/installment/availability' => Http::response([
                'available' => false,
            ]),
        ]);

        $capturedStatus = null;
        Event::listen(\Inkvizitoria\MonoParts\Events\ResponseReceived::class, function ($event) use (&$capturedStatus): void {
            $capturedStatus = $event->response->status;
        });

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);
        $client->brokerAvailability(
            amount: 10.0,
            employeeId: 'emp',
            inn: '12345',
            outletId: 'outlet',
            phone: '+380500000001'
        );

        $this->assertSame(\Inkvizitoria\MonoParts\Status\ResponseStatus::NOT_AVAILABLE, $capturedStatus);
    }

    public function test_validate_client_false_sets_status(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        Http::fake([
            'https://example.test/api/v2/client/validate' => Http::response([
                'found' => false,
            ]),
        ]);

        $capturedStatus = null;
        Event::listen(\Inkvizitoria\MonoParts\Events\ResponseReceived::class, function ($event) use (&$capturedStatus): void {
            $capturedStatus = $event->response->status;
        });

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);
        $client->validateClientV2('+380500000001');

        $this->assertSame(\Inkvizitoria\MonoParts\Status\ResponseStatus::CLIENT_NOT_FOUND, $capturedStatus);
    }

    public function test_duplicate_order_returns_duplicate_status(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        Http::fake([
            'https://example.test/api/order/create' => Http::response([
                'order_id' => '123e4567-e89b-12d3-a456-426614174000',
            ], 409),
        ]);

        $capturedStatus = null;
        Event::listen(\Inkvizitoria\MonoParts\Events\ResponseReceived::class, function ($event) use (&$capturedStatus): void {
            $capturedStatus = $event->response->status;
        });

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);
        $client->createOrder([
            'store_order_id' => 'ORD-1',
            'client_phone' => '+380501234567',
            'total_sum' => 100.00,
            'invoice' => ['date' => '2024-01-01', 'number' => '1', 'source' => 'INTERNET'],
            'available_programs' => [
                ['available_parts_count' => [3], 'type' => 'payment_installments'],
            ],
            'products' => [
                ['name' => 'Test', 'count' => 1, 'sum' => 100.00],
            ],
        ]);

        $this->assertNotNull($capturedStatus);
        $this->assertSame(\Inkvizitoria\MonoParts\Status\ResponseStatus::ORDER_DUPLICATE, $capturedStatus);
    }

    public function test_order_state_maps_success_status(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        Http::fake([
            'https://example.test/api/order/state' => Http::response([
                'order_id' => '123e4567-e89b-12d3-a456-426614174000',
                'state' => 'SUCCESS',
                'order_sub_state' => 'SUCCESS',
            ], 200),
        ]);

        $capturedStatus = null;
        Event::listen(\Inkvizitoria\MonoParts\Events\ResponseReceived::class, function ($event) use (&$capturedStatus): void {
            $capturedStatus = $event->response->status;
        });

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);
        $client->orderState('123e4567-e89b-12d3-a456-426614174000');

        $this->assertSame(\Inkvizitoria\MonoParts\Status\ResponseStatus::ORDER_SUCCESS, $capturedStatus);
    }

    public function test_return_order_maps_error_status_when_not_ok(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        Http::fake([
            'https://example.test/api/order/return' => Http::response([
                'status' => 'FAIL',
            ], 200),
        ]);

        $capturedStatus = null;
        Event::listen(\Inkvizitoria\MonoParts\Events\ResponseReceived::class, function ($event) use (&$capturedStatus): void {
            $capturedStatus = $event->response->status;
        });

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);
        $client->returnOrder('123e4567-e89b-12d3-a456-426614174000', 10.0, true, 'RET-1');

        $this->assertSame(\Inkvizitoria\MonoParts\Status\ResponseStatus::RETURN_ERROR, $capturedStatus);
    }

    public function test_confirm_order_maps_in_process_status(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        $orderId = '123e4567-e89b-12d3-a456-426614174000';

        Http::fake([
            'https://example.test/api/order/confirm' => Http::response([
                'order_id' => $orderId,
                'state' => 'IN_PROCESS',
                'order_sub_state' => 'WAITING_FOR_STORE_CONFIRM',
            ], 200),
        ]);

        $capturedStatus = null;
        Event::listen(\Inkvizitoria\MonoParts\Events\ResponseReceived::class, function ($event) use (&$capturedStatus): void {
            $capturedStatus = $event->response->status;
        });

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);
        $info = $client->confirmOrder($orderId);

        $this->assertSame(\Inkvizitoria\MonoParts\Enums\OrderState::IN_PROCESS, $info->state);
        $this->assertSame(\Inkvizitoria\MonoParts\Status\ResponseStatus::ORDER_IN_PROCESS, $capturedStatus);
    }

    public function test_reject_order_maps_fail_status(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        $orderId = '123e4567-e89b-12d3-a456-426614174000';

        Http::fake([
            'https://example.test/api/order/reject' => Http::response([
                'order_id' => $orderId,
                'state' => 'FAIL',
                'order_sub_state' => 'REJECTED_BY_STORE',
            ], 200),
        ]);

        $capturedStatus = null;
        Event::listen(\Inkvizitoria\MonoParts\Events\ResponseReceived::class, function ($event) use (&$capturedStatus): void {
            $capturedStatus = $event->response->status;
        });

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);
        $info = $client->rejectOrder($orderId);

        $this->assertSame(\Inkvizitoria\MonoParts\Enums\OrderState::FAIL, $info->state);
        $this->assertSame(\Inkvizitoria\MonoParts\Status\ResponseStatus::ORDER_FAIL, $capturedStatus);
    }

    public function test_order_data_maps_response(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        $orderId = '123e4567-e89b-12d3-a456-426614174000';

        Http::fake([
            'https://example.test/api/order/data' => Http::response([
                'store_order_id' => 'ORD-1',
            ], 200),
        ]);

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);
        $info = $client->orderData($orderId);

        $this->assertSame('ORD-1', $info->storeOrderId);
    }

    public function test_store_report_maps_response(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        Http::fake([
            'https://example.test/api/store/report' => Http::response([
                'orders' => [
                    ['order_id' => 'ORD-1'],
                ],
            ], 200),
        ]);

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);
        $report = $client->storeReport('2024-01-01');

        $this->assertCount(1, $report->orders);
        $this->assertSame('ORD-1', $report->orders[0]->orderId);
    }

    public function test_validate_client_true_sets_status(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        Http::fake([
            'https://example.test/api/v2/client/validate' => Http::response([
                'found' => true,
            ], 200),
        ]);

        $capturedStatus = null;
        Event::listen(\Inkvizitoria\MonoParts\Events\ResponseReceived::class, function ($event) use (&$capturedStatus): void {
            $capturedStatus = $event->response->status;
        });

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);
        $client->validateClientV2('+380501234567');

        $this->assertSame(\Inkvizitoria\MonoParts\Status\ResponseStatus::CLIENT_FOUND, $capturedStatus);
    }

    public function test_return_order_maps_ok_status(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        Http::fake([
            'https://example.test/api/order/return' => Http::response([
                'status' => 'OK',
            ], 200),
        ]);

        $capturedStatus = null;
        Event::listen(\Inkvizitoria\MonoParts\Events\ResponseReceived::class, function ($event) use (&$capturedStatus): void {
            $capturedStatus = $event->response->status;
        });

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);
        $client->returnOrder('123e4567-e89b-12d3-a456-426614174000', 10.0, false, 'RET-2');

        $this->assertSame(\Inkvizitoria\MonoParts\Status\ResponseStatus::RETURN_OK, $capturedStatus);
    }

    public function test_transport_exception_is_thrown_for_request_exception(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        $http = new class extends \Illuminate\Http\Client\Factory {
            public function withHeaders($headers): self
            {
                return $this;
            }

            public function post($url, $data = [])
            {
                $psr = new \GuzzleHttp\Psr7\Response(500, [], 'error');
                $response = new \Illuminate\Http\Client\Response($psr);
                throw new \Illuminate\Http\Client\RequestException($response);
            }
        };

        $this->app->instance('http', $http);
        $this->app->forgetInstance(MonoPartsClient::class);

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);

        $this->expectException(\Inkvizitoria\MonoParts\Exceptions\TransportException::class);
        $client->checkPaid('123e4567-e89b-12d3-a456-426614174000');
    }

    public function test_transport_exception_is_thrown_for_unexpected_exception(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        $http = new class extends \Illuminate\Http\Client\Factory {
            public function withHeaders($headers): self
            {
                return $this;
            }

            public function post($url, $data = [])
            {
                throw new \RuntimeException('boom');
            }
        };

        $this->app->instance('http', $http);
        $this->app->forgetInstance(MonoPartsClient::class);

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);

        $this->expectException(\Inkvizitoria\MonoParts\Exceptions\TransportException::class);
        $client->checkPaid('123e4567-e89b-12d3-a456-426614174000');
    }

    public function test_build_response_falls_back_to_http_status(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store-1');

        /** @var MonoPartsClient $client */
        $client = $this->app->make(MonoPartsClient::class);

        $request = new class extends \Inkvizitoria\MonoParts\Http\Requests\MonoPartsRequest {
            public function payload(): array
            {
                return ['foo' => 'bar'];
            }

            public function endpoint(): string
            {
                return '/dummy';
            }
        };

        $reflector = new \ReflectionMethod(MonoPartsClient::class, 'buildResponse');
        $reflector->setAccessible(true);

        $payload = ['foo' => 'bar'];

        $responseOk = new \Illuminate\Http\Client\Response(
            new \GuzzleHttp\Psr7\Response(200, [], json_encode($payload))
        );
        $responseClientError = new \Illuminate\Http\Client\Response(
            new \GuzzleHttp\Psr7\Response(404, [], json_encode($payload))
        );
        $responseServerError = new \Illuminate\Http\Client\Response(
            new \GuzzleHttp\Psr7\Response(500, [], json_encode($payload))
        );

        $monoOk = $reflector->invoke($client, $request, $responseOk);
        $this->assertSame(\Inkvizitoria\MonoParts\Status\ResponseStatus::SUCCESS_HTTP, $monoOk->status);
        $this->assertSame($payload, $monoOk->data);

        $monoClientError = $reflector->invoke($client, $request, $responseClientError);
        $this->assertSame(\Inkvizitoria\MonoParts\Status\ResponseStatus::CLIENT_ERROR_HTTP, $monoClientError->status);

        $monoServerError = $reflector->invoke($client, $request, $responseServerError);
        $this->assertSame(\Inkvizitoria\MonoParts\Status\ResponseStatus::SERVER_ERROR_HTTP, $monoServerError->status);
    }
}
