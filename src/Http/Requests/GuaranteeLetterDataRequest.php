<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

final class GuaranteeLetterDataRequest extends GuaranteeRequest
{
    public function __construct(string $orderId, array $invoice = [], private readonly bool $v2 = false)
    {
        parent::__construct($orderId, $invoice);
    }

    public function endpoint(): string
    {
        return $this->v2 ? '/api/v2/order/data/for/guarantee/letter' : '/api/order/data/for/guarantee/letter';
    }
}
