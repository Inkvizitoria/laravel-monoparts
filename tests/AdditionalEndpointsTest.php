<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Illuminate\Support\Facades\Http;
use Inkvizitoria\MonoParts\Exceptions\PayloadValidationException;
use Inkvizitoria\MonoParts\Exceptions\TransportException;
use Inkvizitoria\MonoParts\Http\MonoPartsClient;

final class AdditionalEndpointsTest extends TestCase
{
    private const ORDER_ID = '123e4567-e89b-12d3-a456-426614174000';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');
        $this->app['config']->set('monoparts.merchant.store_id', 'store');
        Http::preventStrayRequests();
    }

    public function test_guarantee_letter_data_versions_preserve_nested_data(): void
    {
        Http::fake(['*' => Http::response(['header' => ['bank' => ['name' => 'Bank']], 'expansion' => ['amount' => 100]])]);
        $client = $this->app->make(MonoPartsClient::class);
        $data = $client->guaranteeLetterData(self::ORDER_ID, ['date' => '2026-10-03', 'number' => 'INV']);
        $this->assertSame('Bank', $data->header['bank']['name']);
        $this->assertSame(100, $data->expansion['amount']);
        $client->guaranteeLetterDataV2(self::ORDER_ID);
        Http::assertSent(fn ($r) => $r->url() === 'https://example.test/api/order/data/for/guarantee/letter' && $r['invoice']['number'] === 'INV');
        Http::assertSent(fn ($r) => $r->url() === 'https://example.test/api/v2/order/data/for/guarantee/letter');
    }

    public function test_pdf_is_returned_as_original_bytes(): void
    {
        $pdf = "%PDF-1.7\nexample\n%%EOF";
        Http::fake(['*' => Http::response($pdf, 200, ['Content-Type' => 'application/pdf'])]);
        $this->assertSame($pdf, $this->app->make(MonoPartsClient::class)->guaranteeLetter(self::ORDER_ID));
        Http::assertSent(fn ($r) => $r->hasHeader('Accept', 'application/pdf') && $r['order_id'] === self::ORDER_ID);
    }

    public function test_invalid_pdf_is_rejected(): void
    {
        Http::fake(['*' => Http::response('{"message":"not a pdf"}', 200)]);
        $this->expectException(TransportException::class);
        $this->app->make(MonoPartsClient::class)->guaranteeLetter(self::ORDER_ID);
    }

    public function test_create_and_cancel_qr_cart(): void
    {
        Http::fake([
            'https://example.test/api/v1/qr/cart' => Http::response(['id' => self::ORDER_ID], 201),
            'https://example.test/api/v1/qr/cart/cancel' => Http::response('', 200),
        ]);
        $client = $this->app->make(MonoPartsClient::class);
        $cart = $client->createQrCart([
            'qr_id' => 'qr-1', 'store_order_id' => self::ORDER_ID,
            'products' => [['name' => 'Product', 'count' => 2, 'sum' => '10.50']],
            'result_callback' => 'https://shop.example/callback',
        ]);
        $this->assertSame(self::ORDER_ID, $cart->id);
        $client->cancelQrCart('qr-1');
        Http::assertSent(fn ($r) => str_contains($r->body(), '"sum":10.50'));
        Http::assertSent(fn ($r) => $r->url() === 'https://example.test/api/v1/qr/cart/cancel' && $r['qr_id'] === 'qr-1');
    }

    public function test_invalid_invoice_is_rejected_before_http(): void
    {
        Http::fake();
        $this->expectException(PayloadValidationException::class);
        $this->app->make(MonoPartsClient::class)->guaranteeLetter(self::ORDER_ID, ['date' => 'yesterday']);
    }

    public function test_qr_requires_callback_and_uuid_store_order_id(): void
    {
        Http::fake();
        $this->expectException(PayloadValidationException::class);
        $this->app->make(MonoPartsClient::class)->createQrCart(['qr_id' => 'qr-1', 'store_order_id' => 'ORD-1']);
    }
}
