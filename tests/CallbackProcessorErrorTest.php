<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Contracts\SignerInterface;
use Inkvizitoria\MonoParts\Security\JsonBodyBuilder;

final class CallbackProcessorErrorTest extends TestCase
{
    public function test_callback_returns_server_error_on_exception(): void
    {
        $this->app->bind(SignerInterface::class, function (): SignerInterface {
            return new class implements SignerInterface {
                public function sign(string $body): string
                {
                    throw new \RuntimeException('boom');
                }

                public function verify(string $body, string $signature): bool
                {
                    throw new \RuntimeException('boom');
                }

                public function assertValid(string $body, string $signature): void
                {
                    throw new \RuntimeException('boom');
                }
            };
        });

        $payload = [
            'order_id' => '123e4567-e89b-12d3-a456-426614174000',
            'state' => 'SUCCESS',
        ];

        $builder = $this->app->make(JsonBodyBuilder::class);
        $rawBody = $builder->build($payload);

        $response = $this->call('POST', '/monoparts/callback', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_signature' => 'any',
        ], $rawBody);

        $response->assertStatus(500);
    }
}
