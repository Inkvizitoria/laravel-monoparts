<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Events;

use Inkvizitoria\MonoParts\Http\MonoPartsResponse;

/**
 * @psalm-immutable
 */
final class ResponseReceived
{
    public function __construct(
        public readonly MonoPartsResponse $response,
    ) {
    }
}
