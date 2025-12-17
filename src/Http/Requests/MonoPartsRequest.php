<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

abstract class MonoPartsRequest
{
    /**
     * @return array<string, mixed>
     */
    abstract public function payload(): array;

    /**
     * Validation rules for the payload.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Custom headers that should be added for this request.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [];
    }

    /**
     * Whether store-id header is required.
     */
    public function requiresStoreId(): bool
    {
        return true;
    }

    /**
     * Whether a broker-id header is required.
     */
    public function requiresBrokerId(): bool
    {
        return false;
    }

    /**
     * Optional broker-id override provided directly with the request.
     */
    public function brokerId(): ?string
    {
        return null;
    }

    /**
     * Validate payload against rules and return sanitized version.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function validate(array $payload): array
    {
        if ($this->rules() === []) {
            return $payload;
        }

        $validator = \Illuminate\Support\Facades\Validator::make($payload, $this->rules());

        return $validator->validate();
    }

    /**
     * Endpoint relative path to be appended to the configured base URL.
     */
    abstract public function endpoint(): string;
}
