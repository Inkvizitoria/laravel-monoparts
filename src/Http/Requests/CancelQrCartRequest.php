<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

final class CancelQrCartRequest extends MonoPartsRequest
{
    public function __construct(private readonly string $qrId)
    {
    }

    public function payload(): array
    {
        return ['qr_id' => $this->qrId];
    }

    public function rules(): array
    {
        return ['qr_id' => ['required', 'string', 'min:1']];
    }

    public function endpoint(): string
    {
        return '/api/v1/qr/cart/cancel';
    }
}
