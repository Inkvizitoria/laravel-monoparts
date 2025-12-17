<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Enums;

/**
 * High level order state derived from API responses.
 */
enum OrderState: string
{
    case SUCCESS = 'SUCCESS';
    case FAIL = 'FAIL';
    case IN_PROCESS = 'IN_PROCESS';
}
