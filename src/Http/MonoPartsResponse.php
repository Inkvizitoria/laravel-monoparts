<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http;

use Inkvizitoria\MonoParts\Status\ResponseStatus;
use Illuminate\Http\Client\Response;

final class MonoPartsResponse
{
    /**
     * @param array<string, mixed>|null $raw
     */
    public function __construct(
        public readonly ResponseStatus $status,
        public readonly int $httpStatus,
        public readonly ?array $raw,
        public readonly mixed $data = null,
        public readonly array $headers = [],
    ) {
    }

    /**
     * Build instance from an HTTP response.
     */
    public static function fromHttp(Response $response, ResponseStatus $status, mixed $data = null): self
    {
        return new self(
            status: $status,
            httpStatus: $response->status(),
            raw: $response->json(),
            data: $data,
            headers: $response->headers(),
        );
    }

    /**
     * Whether business status represents a success outcome.
     */
    public function successful(): bool
    {
        return match ($this->status) {
            ResponseStatus::ORDER_SUCCESS,
            ResponseStatus::ORDER_CREATED,
            ResponseStatus::ORDER_DUPLICATE,
            ResponseStatus::ORDER_IN_PROCESS,
            ResponseStatus::RETURN_OK,
            ResponseStatus::CHECK_PAID_YES,
            ResponseStatus::AVAILABLE,
            ResponseStatus::CLIENT_FOUND,
            ResponseStatus::SUCCESS_HTTP => true,
            default => false,
        };
    }
}
