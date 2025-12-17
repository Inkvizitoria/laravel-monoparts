<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Enums;

/**
 * Detailed order sub-states as documented by Monobank.
 */
enum OrderSubState: string
{
    case ADDED = 'ADDED';
    case INTERNAL_INIT = 'INTERNAL_INIT';
    case INTERNAL_INIT_PRE_ACTIVATE = 'INTERNAL_INIT_PRE_ACTIVATE';
    case INTERNAL_INIT_DEBIT = 'INTERNAL_INIT_DEBIT';
    case TESTING = 'TESTING';
    case INTERNAL_ADDED = 'INTERNAL_ADDED';
    case INTERNAL_CHECKED = 'INTERNAL_CHECKED';
    case INTERNAL_WAITING_FOR_IBUS_PDFBOX = 'INTERNAL_WAITING_FOR_IBUS_PDFBOX';
    case CLIENT_NOT_FOUND = 'CLIENT_NOT_FOUND';
    case WRONG_CLIENT_APP_VERSION = 'WRONG_CLIENT_APP_VERSION';
    case EXCEEDED_SUM_LIMIT = 'EXCEEDED_SUM_LIMIT';
    case ACCOUNT_CLOSED = 'ACCOUNT_CLOSED';
    case PAY_PARTS_ARE_NOT_ACCEPTABLE = 'PAY_PARTS_ARE_NOT_ACCEPTABLE';
    case CLIENT_CONFIRM_TIME_EXPIRED = 'CLIENT_CONFIRM_TIME_EXPIRED';
    case WAITING_FOR_CLIENT = 'WAITING_FOR_CLIENT';
    case REJECTED_BY_CLIENT = 'REJECTED_BY_CLIENT';
    case REJECTED_BY_STORE = 'REJECTED_BY_STORE';
    case WAITING_FOR_STORE_CONFIRM = 'WAITING_FOR_STORE_CONFIRM';
    case SUCCESS = 'SUCCESS';

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value);
    }
}
