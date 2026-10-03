<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

final class GuaranteeLetterRequest extends GuaranteeRequest
{
    public function headers(): array
    {
        return ['Accept' => 'application/pdf'];
    }

    public function endpoint(): string
    {
        return '/api/order/guarantee/letter';
    }
}
