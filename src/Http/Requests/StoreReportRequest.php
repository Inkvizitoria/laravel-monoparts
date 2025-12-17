<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Requests;

/**
 * Request definition for /api/store/report.
 */
final class StoreReportRequest extends MonoPartsRequest
{
    public function __construct(
        private readonly string $date,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return ['date' => $this->date];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Endpoint path.
     */
    public function endpoint(): string
    {
        return '/api/store/report';
    }
}
