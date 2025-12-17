<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Enums;

/**
 * @psalm-immutable
 */
enum Environment: string
{
    case SANDBOX = 'sandbox';
    case STAGE = 'stage';
    case PRODUCTION = 'production';
}
