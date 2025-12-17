<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Enums;

/**
 * Status returned by returnOrder endpoint.
 */
enum ReturnStatus: string
{
    case OK = 'OK';
    case ERROR = 'ERROR';

    public static function fromRaw(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::ERROR;
        }

        return self::tryFrom($value) ?? self::ERROR;
    }
}
