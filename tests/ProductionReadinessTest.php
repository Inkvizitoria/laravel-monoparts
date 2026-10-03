<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Inkvizitoria\MonoParts\Events\CallbackFailed;
use Inkvizitoria\MonoParts\Events\CallbackReceived;
use Inkvizitoria\MonoParts\Events\CallbackValidated;
use Inkvizitoria\MonoParts\Exceptions\ApiResponseException;
use Inkvizitoria\MonoParts\Exceptions\PayloadValidationException;
use Inkvizitoria\MonoParts\Exceptions\TransportException;
use Inkvizitoria\MonoParts\Http\MonoPartsClient;
use Inkvizitoria\MonoParts\Http\Responses\DailyReportOrder;
use Inkvizitoria\MonoParts\Security\JsonBodyBuilder;
use Inkvizitoria\MonoParts\Security\RawJsonNumber;
use Inkvizitoria\MonoParts\Security\Signer;
use Inkvizitoria\MonoParts\Validation\MoneyRule;
use Inkvizitoria\MonoParts\ValueObjects\Money;
use InvalidArgumentException;

final class ProductionReadinessTest extends TestCase
{
    private const ORDER_ID = '123e4567-e89b-12d3-a456-426614174000';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store');
        $this->app['config']->set('monoparts.merchant.broker_id', 'broker');
        Http::preventStrayRequests();
    }

    public function test_integer_amounts_are_major_units_and_cents_are_explicit(): void
    {
        $this->assertSame('100.00', Money::fromNumber(100)->toDecimal());
        $this->assertSame('100.00', Money::fromNumber('100')->toDecimal());
        $this->assertSame('1.00', Money::fromCents(100)->toDecimal());
        $this->assertSame('0.00', Money::fromCents(0)->toDecimal());
        $this->assertSame('100.00', DailyReportOrder::fromPayload(['total_sum' => 100])->totalSum?->toDecimal());
        $this->assertSame('0.00', DailyReportOrder::fromPayload(['commission' => 0])->commission?->toDecimal());
        $this->assertNull(DailyReportOrder::fromPayload(['create_date_time' => 'invalid'])->createDateTime);
    }

    public function test_money_rejects_extra_precision_instead_of_rounding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromNumber(1.005);
    }

    public function test_money_rejects_overflow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimal('922337203685477580.00');
    }

    public function test_money_addition_rejects_overflow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromCents(PHP_INT_MAX)->add(Money::fromCents(1));
    }

    public function test_money_rule_handles_invalid_types_without_type_errors(): void
    {
        foreach ([null, [], true, new \stdClass(), '1.005', INF, NAN, -1, 0] as $value) {
            $this->assertFalse((new MoneyRule())->passes('sum', $value));
        }
    }

    public function test_raw_number_cannot_generate_invalid_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RawJsonNumber('01.00');
    }

    public function test_empty_request_is_a_json_object(): void
    {
        $this->assertSame('{}', (new JsonBodyBuilder())->build([]));
    }

    public function test_create_order_serializes_all_money_fields_and_signs_exact_body(): void
    {
        Http::fake(['*' => Http::response(['order_id' => self::ORDER_ID], 201)]);
        $payload = $this->createPayload();
        $payload['total_sum'] = Money::fromDecimal('100.25');
        $payload['additional_params'] = ['nds' => 0, 'ext_initial_sum' => '10.50'];
        $this->app->make(MonoPartsClient::class)->createOrder($payload);
        Http::assertSent(function ($request): bool {
            $body = $request->body();
            return str_contains($body, '"total_sum":100.25')
                && str_contains($body, '"sum":100.25')
                && str_contains($body, '"nds":0.00')
                && str_contains($body, '"ext_initial_sum":10.50')
                && $request->hasHeader('signature', (new Signer('secret'))->sign($body));
        });
    }

    public function test_public_refund_and_broker_methods_accept_money_and_decimal_strings(): void
    {
        Http::fake(['*' => Http::response(['status' => 'OK', 'available' => true])]);
        $client = $this->app->make(MonoPartsClient::class);
        $this->assertSame('OK', $client->returnOrder(self::ORDER_ID, Money::fromDecimal('10.50'), false, 'RET')->status->value);
        $this->assertTrue($client->brokerAvailability('100.00', 'employee', '123', 'outlet', '+380500000001')->available);
        Http::assertSentCount(2);
    }

    public function test_invalid_money_payload_never_sends_http_request(): void
    {
        Http::fake();
        $payload = $this->createPayload();
        $payload['products'][0]['sum'] = [];
        try {
            $this->app->make(MonoPartsClient::class)->createOrder($payload);
            $this->fail('Invalid money must be rejected.');
        } catch (PayloadValidationException $e) {
            $this->assertArrayHasKey('products.0.sum', $e->errors());
            Http::assertNothingSent();
        }
    }

    public function test_non_json_api_error_keeps_http_status(): void
    {
        Http::fake(['*' => Http::response('<html>Unavailable</html>', 503)]);
        try {
            $this->app->make(MonoPartsClient::class)->checkPaid(self::ORDER_ID);
            $this->fail('HTTP error must be surfaced.');
        } catch (ApiResponseException $e) {
            $this->assertSame(503, $e->statusCode);
        }
    }

    public function test_malformed_success_json_is_not_a_success_dto(): void
    {
        Http::fake(['*' => Http::response('not json', 200)]);
        $this->expectException(TransportException::class);
        $this->app->make(MonoPartsClient::class)->checkPaid(self::ORDER_ID);
    }

    public function test_scalar_error_json_is_normalized(): void
    {
        Http::fake(['*' => Http::response('"error"', 400)]);
        $this->expectException(ApiResponseException::class);
        $this->app->make(MonoPartsClient::class)->checkPaid(self::ORDER_ID);
    }

    public function test_create_without_order_id_is_not_success(): void
    {
        Http::fake(['*' => Http::response(['message' => 'conflict'], 409)]);
        $this->expectException(TransportException::class);
        $this->app->make(MonoPartsClient::class)->createOrder($this->createPayload());
    }

    public function test_signed_invalid_callback_emits_received_once(): void
    {
        Event::fake();
        $body = '{"order_id":"' . self::ORDER_ID . '"}';
        $this->sendCallback($body)->assertStatus(400);
        Event::assertDispatchedTimes(CallbackReceived::class, 1);
        Event::assertDispatchedTimes(CallbackFailed::class, 1);
        Event::assertNotDispatched(CallbackValidated::class);
    }

    public function test_callback_listener_failure_returns_500_without_repeating_received(): void
    {
        $received = 0;
        Event::listen(CallbackReceived::class, function () use (&$received): void { ++$received; });
        Event::listen(CallbackValidated::class, function (): void { throw new \RuntimeException('handler failed'); });
        $this->sendCallback('{"order_id":"' . self::ORDER_ID . '","state":"SUCCESS"}')->assertStatus(500);
        $this->assertSame(1, $received);
    }

    public function test_callback_uses_original_bytes_and_accepts_future_substate(): void
    {
        Event::fake();
        $body = '{ "state": "SUCCESS", "order_id": "' . self::ORDER_ID . '", "order_sub_state": "FUTURE_STATE" }';
        $this->sendCallback($body)->assertStatus(200);
        Event::assertDispatched(CallbackValidated::class, fn ($event) => $event->stateInfo->rawOrderSubState === 'FUTURE_STATE');
        $this->call('POST', '/monoparts/callback', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_signature' => (new Signer('secret'))->sign($body),
        ], $body . ' ')->assertStatus(403);
    }

    public function test_boolean_response_cannot_use_string_false(): void
    {
        Http::fake(['*' => Http::response(['found' => 'false'])]);
        $this->expectException(TransportException::class);
        $this->app->make(MonoPartsClient::class)->validateClientV2('+380500000001');
    }

    public function test_empty_object_cannot_pass_as_paid_response(): void
    {
        Http::fake(['*' => Http::response('{}')]);
        $this->expectException(TransportException::class);
        $this->app->make(MonoPartsClient::class)->checkPaid(self::ORDER_ID);
    }

    public function test_product_lists_and_integer_fields_use_json_schema_types(): void
    {
        Http::fake(['*' => Http::response(['order_id' => self::ORDER_ID], 201)]);
        $payload = $this->createPayload();
        $payload['products'][0]['count'] = '2';
        $payload['available_programs'][0]['available_parts_count'] = ['3'];
        $this->app->make(MonoPartsClient::class)->createOrder($payload);
        Http::assertSent(fn ($request) => str_contains($request->body(), '"count":2') && str_contains($request->body(), '"available_parts_count":[3]'));
    }

    public function test_associative_product_array_is_rejected(): void
    {
        $payload = $this->createPayload();
        $payload['products'] = ['sku' => $payload['products'][0]];
        $this->expectException(PayloadValidationException::class);
        $this->app->make(MonoPartsClient::class)->createOrder($payload);
    }

    public function test_large_float_does_not_depend_on_php_precision_setting(): void
    {
        $previous = ini_set('precision', '14');
        try {
            $this->assertSame('1234567890123.45', Money::fromNumber(1234567890123.45)->toDecimal());
        } finally {
            ini_set('precision', $previous);
        }
    }

    public function test_extra_float_precision_is_rejected_even_if_string_cast_rounds_it(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromNumber(1.000000000000001);
    }

    public function test_empty_success_responses_are_rejected_for_all_json_document_endpoints(): void
    {
        Http::fake(['*' => Http::response('{}')]);
        $client = $this->app->make(MonoPartsClient::class);
        foreach ([
            ['orderState', [self::ORDER_ID]], ['confirmOrder', [self::ORDER_ID]],
            ['rejectOrder', [self::ORDER_ID]], ['orderData', [self::ORDER_ID]],
            ['storeReport', ['2026-10-03']], ['returnOrder', [self::ORDER_ID, '1.00', false, 'RET']],
            ['guaranteeLetterData', [self::ORDER_ID]], ['guaranteeLetterDataV2', [self::ORDER_ID]],
        ] as [$method, $args]) {
            try {
                $client->{$method}(...$args);
                $this->fail($method . ' must reject an empty response.');
            } catch (TransportException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function test_empty_optional_objects_are_omitted_from_create_payload(): void
    {
        Http::fake(['*' => Http::response(['order_id' => self::ORDER_ID], 201)]);
        $payload = $this->createPayload();
        $payload['additional_params'] = [];
        $payload['financial_company_merchant_info'] = [];
        $this->app->make(MonoPartsClient::class)->createOrder($payload);
        Http::assertSent(fn ($r) => !str_contains($r->body(), 'additional_params') && !str_contains($r->body(), 'financial_company_merchant_info'));
    }

    public function test_invalid_utf8_is_a_payload_error_before_transport(): void
    {
        Http::fake();
        $payload = $this->createPayload();
        $payload['products'][0]['name'] = "Product " . chr(255);
        $this->expectException(PayloadValidationException::class);
        $this->app->make(MonoPartsClient::class)->createOrder($payload);
    }

    public function test_unknown_order_state_is_preserved_without_reporting_success(): void
    {
        Http::fake(['*' => Http::response(['order_id' => self::ORDER_ID, 'state' => 'FUTURE_STATE'])]);
        $response = null;
        Event::listen(\Inkvizitoria\MonoParts\Events\ResponseReceived::class, function ($event) use (&$response): void {
            $response = $event->response;
        });
        $info = $this->app->make(MonoPartsClient::class)->orderState(self::ORDER_ID);
        $this->assertSame('FUTURE_STATE', $info->rawState);
        $this->assertFalse($response->successful());
    }

    private function sendCallback(string $body)
    {
        return $this->call('POST', '/monoparts/callback', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_signature' => (new Signer('secret'))->sign($body),
        ], $body);
    }

    private function createPayload(): array
    {
        return [
            'store_order_id' => 'ORD-1', 'client_phone' => '+380500000001', 'total_sum' => '100.25',
            'invoice' => ['date' => '2026-10-03', 'number' => 'INV-1', 'source' => 'INTERNET'],
            'available_programs' => [['available_parts_count' => [3], 'type' => 'payment_installments']],
            'products' => [['name' => 'Product', 'count' => 1, 'sum' => '100.25']],
        ];
    }
}
