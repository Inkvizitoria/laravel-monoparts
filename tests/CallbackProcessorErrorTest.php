<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Contracts\SignerInterface;

final class CallbackProcessorErrorTest extends TestCase
{
    public function test_callback_returns_server_error_on_exception(): void
    {
        $this->app->bind(SignerInterface::class, function (): SignerInterface {
            return new class implements SignerInterface {
                public function sign(array $payload): string
                {
                    throw new \RuntimeException('boom');
                }

                public function verify(array $payload, string $signature): bool
                {
                    throw new \RuntimeException('boom');
                }
            };
        });

        $payload = [
            'order_id' => '123e4567-e89b-12d3-a456-426614174000',
            'state' => 'SUCCESS',
        ];

        $response = $this->postJson('/monoparts/callback', $payload, [
            'signature' => 'any',
        ]);

        $response->assertStatus(500);
    }
}
