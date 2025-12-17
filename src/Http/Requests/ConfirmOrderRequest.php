<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

final class ConfirmOrderRequest extends OrderIdRequest
{
    /**
     * Endpoint path.
     */
    public function endpoint(): string
    {
        return '/api/order/confirm';
    }
}
