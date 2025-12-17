<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Support\HmacSigner;

final class SignerTest extends TestCase
{
    public function test_sign_and_verify_roundtrip(): void
    {
        $signer = new HmacSigner('secret', 'sha256');
        $payload = ['a' => 1, 'b' => 'two'];

        $signature = $signer->sign($payload);

        $this->assertNotEmpty($signature);
        $this->assertTrue($signer->verify($payload, $signature));
        $this->assertFalse($signer->verify(['a' => 1], $signature));
    }

    public function test_empty_secret_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        new HmacSigner('');
    }
}
