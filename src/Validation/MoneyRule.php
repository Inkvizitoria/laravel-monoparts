<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Validation;

use Illuminate\Contracts\Validation\Rule;
use Inkvizitoria\MonoParts\ValueObjects\Money;

final class MoneyRule implements Rule
{
    public function __construct(private readonly int $minimumCents = 1)
    {
    }

    public function passes($attribute, $value): bool
    {
        $money = Money::tryFromMixed($value);

        return $money !== null && $money->toCents() >= $this->minimumCents;
    }

    public function message(): string
    {
        return 'The :attribute must be a non-negative amount with at most two decimal places and meet the minimum amount.';
    }
}
