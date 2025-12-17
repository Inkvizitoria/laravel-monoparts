<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Events\CallbackValidated;
use Illuminate\Support\Facades\Event;

final class CallbackProcessorTest extends TestCase
{
    public function test_callback_signature_validation(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');

        $payload = [
            'order_id' => '123e4567-e89b-12d3-a456-426614174000',
            'state' => 'SUCCESS',
            'order_sub_state' => 'SUCCESS',
        ];

        $signer = new \Inkvizitoria\MonoParts\Support\HmacSigner('secret');
        $signature = $signer->sign($payload);

        Event::fake();

        $response = $this->postJson('/monoparts/callback', $payload, [
            'signature' => $signature,
        ]);

        $response->assertStatus(200);
        Event::assertDispatched(CallbackValidated::class, function (CallbackValidated $event) use ($payload): bool {
            return $event->payload === $payload
                && $event->stateInfo !== null
                && $event->stateInfo->orderId === $payload['order_id'];
        });
    }

    public function test_callback_validation_error_returns_bad_request(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');

        $payload = [
            'order_id' => '123e4567-e89b-12d3-a456-426614174000',
        ];
        $signer = new \Inkvizitoria\MonoParts\Support\HmacSigner('secret');
        $signature = $signer->sign($payload);

        $response = $this->postJson('/monoparts/callback', $payload, [
            'signature' => $signature,
        ]);

        $response->assertStatus(400);
    }

    public function test_callback_with_invalid_signature_is_rejected(): void
    {
        $this->app['config']->set('monoparts.merchant.signature_secret', 'secret');

        $payload = [
            'order_id' => '123e4567-e89b-12d3-a456-426614174000',
            'state' => 'SUCCESS',
        ];

        $response = $this->postJson('/monoparts/callback', $payload, [
            'signature' => 'invalid-signature',
        ]);

        $response->assertStatus(403);
    }
}
