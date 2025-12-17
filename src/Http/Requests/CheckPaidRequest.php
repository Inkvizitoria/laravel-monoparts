<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

/**
 * Request definition for /api/order/check/paid.
 */
final class CheckPaidRequest extends OrderIdRequest
{
    /**
     * Endpoint path.
     */
    public function endpoint(): string
    {
        return '/api/order/check/paid';
    }
}
