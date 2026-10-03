<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Responses;

/** Bank document data; nested fields retain the bank's schema and numeric units. */
final class GuaranteeLetterData
{
    public function __construct(
        public readonly array $header,
        public readonly array $expansion,
        public readonly array $raw,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            is_array($payload['header'] ?? null) ? $payload['header'] : [],
            is_array($payload['expansion'] ?? null) ? $payload['expansion'] : [],
            $payload,
        );
    }
}
