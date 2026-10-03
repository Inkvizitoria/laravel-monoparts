<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\ValueObjects;

use InvalidArgumentException;

/** Non-negative UAH amount stored as integer kopiykas. */
final class Money
{
    private function __construct(private readonly int $cents)
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }
    }

    /** Parse an exact decimal amount with two fractional digits. */
    public static function fromDecimal(string $value): self
    {
        if (preg_match('/^(0|[1-9][0-9]*)\.[0-9]{2}$/D', $value) !== 1) {
            throw new InvalidArgumentException('Money must be a non-negative decimal with two fractional digits.');
        }

        $digits = ltrim(str_replace('.', '', $value), '0');
        $max = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            throw new InvalidArgumentException('Money exceeds the integer range.');
        }

        return new self((int) $digits);
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    /** Integers, floats and strings are UAH, never cents. No rounding is performed. */
    public static function fromNumber(int|float|string $value): self
    {
        if (is_float($value) && !is_finite($value)) {
            throw new InvalidArgumentException('Money must be finite.');
        }

        if (is_float($value)) {
            // At 2^46 and above, adjacent floats can be more than one cent apart.
            if ($value >= 2 ** 46) {
                throw new InvalidArgumentException('Use a decimal string for amounts beyond float cent precision.');
            }
            $decimal = sprintf('%.2F', $value);
            if ((float) $decimal !== $value) {
                throw new InvalidArgumentException('Money must have at most two fractional digits.');
            }
        } else {
            $decimal = (string) $value;
        }
        if (preg_match('/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/D', $decimal) !== 1) {
            throw new InvalidArgumentException('Money must have at most two fractional digits.');
        }

        [$major, $minor] = array_pad(explode('.', $decimal, 2), 2, '');

        return self::fromDecimal($major . '.' . str_pad($minor, 2, '0'));
    }

    public static function fromMixed(self|int|float|string $value): self
    {
        return $value instanceof self ? $value : self::fromNumber($value);
    }

    public static function tryFromMixed(mixed $value): ?self
    {
        if (!$value instanceof self && !is_int($value) && !is_float($value) && !is_string($value)) {
            return null;
        }

        try {
            return self::fromMixed($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function toCents(): int
    {
        return $this->cents;
    }

    public function toDecimal(): string
    {
        return sprintf('%d.%02d', intdiv($this->cents, 100), $this->cents % 100);
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function add(self $other): self
    {
        if ($other->cents > PHP_INT_MAX - $this->cents) {
            throw new InvalidArgumentException('Money exceeds the integer range.');
        }

        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }
}
