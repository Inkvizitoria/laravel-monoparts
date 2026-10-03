<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Security;

use InvalidArgumentException;

/**
 * Wrapper for numeric JSON literals (serialized without quotes).
 */
final class RawJsonNumber
{
    private const NUMBER_PATTERN = '/^(0|[1-9][0-9]*)\.[0-9]{2}$/D';

    public function __construct(
        private readonly string $value,
    ) {
        if (!preg_match(self::NUMBER_PATTERN, $this->value)) {
            throw new InvalidArgumentException('RawJsonNumber must be a non-negative JSON number with two fractional digits.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
