<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Responses;

final class QrCartResult
{
    public function __construct(public readonly string $id)
    {
    }

    public static function fromPayload(array $payload): self
    {
        return new self($payload['id']);
    }
}
