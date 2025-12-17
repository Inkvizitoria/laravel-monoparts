<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

/**
 * Request definition for /api/v2/client/validate.
 */
final class ClientValidateV2Request extends MonoPartsRequest
{
    public function __construct(
        private readonly ?string $phone,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $payload = [];

        if ($this->phone !== null) {
            $payload['phone'] = $this->phone;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'regex:/^\\+380\\d{9}$/'],
        ];
    }

    /**
     * Endpoint path.
     */
    public function endpoint(): string
    {
        return '/api/v2/client/validate';
    }
}
