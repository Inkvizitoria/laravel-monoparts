<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Tests;

use Inkvizitoria\MonoParts\Exceptions\InvalidSignatureException;
use Inkvizitoria\MonoParts\Security\Signer;

final class SignerTest extends TestCase
{
    public function test_sign_and_verify_roundtrip(): void
    {
        $signer = new Signer('secret', 'sha256');
        $body = '{"a":1,"b":"two"}';

        $signature = $signer->sign($body);

        $this->assertNotEmpty($signature);
        $this->assertTrue($signer->verify($body, $signature));
        $this->assertFalse($signer->verify('{"a":1}', $signature));
    }

    public function test_empty_secret_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        new Signer('');
    }

    public function test_assert_valid_throws_on_invalid_signature(): void
    {
        $signer = new Signer('secret', 'sha256');

        $this->expectException(InvalidSignatureException::class);
        $signer->assertValid('{"a":1}', 'invalid');
    }
}
