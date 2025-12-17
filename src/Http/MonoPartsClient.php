<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http;

use Inkvizitoria\MonoParts\Config\MonoPartsConfig;
use Inkvizitoria\MonoParts\Contracts\SignerInterface;
use Inkvizitoria\MonoParts\Events\RequestSending;
use Inkvizitoria\MonoParts\Events\ResponseReceived;
use Inkvizitoria\MonoParts\Exceptions\ApiResponseException;
use Inkvizitoria\MonoParts\Exceptions\ConfigurationException;
use Inkvizitoria\MonoParts\Exceptions\PayloadValidationException;
use Inkvizitoria\MonoParts\Exceptions\TransportException;
use Inkvizitoria\MonoParts\Http\Requests\BrokerAvailabilityRequest;
use Inkvizitoria\MonoParts\Http\Requests\CheckPaidRequest;
use Inkvizitoria\MonoParts\Http\Requests\ClientValidateV2Request;
use Inkvizitoria\MonoParts\Http\Requests\ConfirmOrderRequest;
use Inkvizitoria\MonoParts\Http\Requests\CreateOrderRequest;
use Inkvizitoria\MonoParts\Http\Requests\MonoPartsRequest;
use Inkvizitoria\MonoParts\Http\Requests\OrderDataRequest;
use Inkvizitoria\MonoParts\Http\Requests\OrderStateRequest;
use Inkvizitoria\MonoParts\Http\Requests\RejectOrderRequest;
use Inkvizitoria\MonoParts\Http\Requests\ReturnOrderRequest;
use Inkvizitoria\MonoParts\Http\Requests\StoreReportRequest;
use Inkvizitoria\MonoParts\Http\Responses\CheckPaidResult;
use Inkvizitoria\MonoParts\Http\Responses\CreateOrderResult;
use Inkvizitoria\MonoParts\Http\Responses\DailyReport;
use Inkvizitoria\MonoParts\Http\ExceptionResponse;
use Inkvizitoria\MonoParts\Http\Responses\InstallmentAvailabilityResponse;
use Inkvizitoria\MonoParts\Http\Responses\OrderShortInfo;
use Inkvizitoria\MonoParts\Http\Responses\OrderStateInfo;
use Inkvizitoria\MonoParts\Http\Responses\ReturnResponse;
use Inkvizitoria\MonoParts\Http\Responses\ValidateClientResponse;
use Inkvizitoria\MonoParts\Support\MonoPartsLogger;
use Inkvizitoria\MonoParts\Status\ResponseStatus;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MonoPartsClient
{
    public function __construct(
        private readonly Factory $http,
        private readonly MonoPartsConfig $config,
        private readonly SignerInterface $signer,
        private readonly Dispatcher $events,
        private readonly MonoPartsLogger $logger,
    ) {
    }

    /**
     * Check whether an order is fully paid.
     *
     * @param string $orderId
     * @return CheckPaidResult
     */
    public function checkPaid(string $orderId): CheckPaidResult
    {
        $response = $this->send(new CheckPaidRequest($orderId));

        /** @var CheckPaidResult $dto */
        $dto = $response->data;

        return $dto;
    }

    /**
     * Create a new installment order.
     *
     * @param array<string, mixed> $payload
     * @return CreateOrderResult
     */
    public function createOrder(array $payload): CreateOrderResult
    {
        $response = $this->send(new CreateOrderRequest($payload));

        /** @var CreateOrderResult $dto */
        $dto = $response->data;

        return $dto;
    }

    /**
     * Confirm delivery of goods to the customer.
     *
     * @param string $orderId
     * @return OrderStateInfo
     */
    public function confirmOrder(string $orderId): OrderStateInfo
    {
        $response = $this->send(new ConfirmOrderRequest($orderId));

        /** @var OrderStateInfo $dto */
        $dto = $response->data;

        return $dto;
    }

    /**
     * Retrieve current order details.
     *
     * @param string $orderId
     * @return OrderShortInfo
     */
    public function orderData(string $orderId): OrderShortInfo
    {
        $response = $this->send(new OrderDataRequest($orderId));

        /** @var OrderShortInfo $dto */
        $dto = $response->data;

        return $dto;
    }

    /**
     * Cancel order before delivery.
     *
     * @param string $orderId
     * @return OrderStateInfo
     */
    public function rejectOrder(string $orderId): OrderStateInfo
    {
        $response = $this->send(new RejectOrderRequest($orderId));

        /** @var OrderStateInfo $dto */
        $dto = $response->data;

        return $dto;
    }

    /**
     * Apply full or partial return.
     *
     * @param string $orderId
     * @param float $sum
     * @param bool $returnMoneyToCard
     * @param string $storeReturnId
     * @param array<string, mixed> $additionalParams
     * @return ReturnResponse
     */
    public function returnOrder(string $orderId, float $sum, bool $returnMoneyToCard, string $storeReturnId, array $additionalParams = []): ReturnResponse
    {
        $response = $this->send(new ReturnOrderRequest(
            orderId: $orderId,
            sum: $sum,
            returnMoneyToCard: $returnMoneyToCard,
            storeReturnId: $storeReturnId,
            additionalParams: $additionalParams
        ));

        /** @var ReturnResponse $dto */
        $dto = $response->data;

        return $dto;
    }

    /**
     * Fetch order state (callback-friendly).
     *
     * @param string $orderId
     * @return OrderStateInfo
     */
    public function orderState(string $orderId): OrderStateInfo
    {
        $response = $this->send(new OrderStateRequest($orderId));

        /** @var OrderStateInfo $dto */
        $dto = $response->data;

        return $dto;
    }

    /**
     * Fetch daily store report.
     *
     * @param string $date
     * @return DailyReport
     */
    public function storeReport(string $date): DailyReport
    {
        $response = $this->send(new StoreReportRequest($date));

        /** @var DailyReport $dto */
        $dto = $response->data;

        return $dto;
    }

    /**
     * Validate client eligibility (v2 endpoint).
     *
     * @param string|null $phone
     * @return ValidateClientResponse
     */
    public function validateClientV2(?string $phone = null): ValidateClientResponse
    {
        $response = $this->send(new ClientValidateV2Request($phone));

        /** @var ValidateClientResponse $dto */
        $dto = $response->data;

        return $dto;
    }

    /**
     * Check installment availability for brokers.
     *
     * @param float $amount
     * @param string $employeeId
     * @param string $inn
     * @param string $outletId
     * @param string $phone
     * @param string|null $brokerId
     * @return InstallmentAvailabilityResponse
     */
    public function brokerAvailability(float $amount, string $employeeId, string $inn, string $outletId, string $phone, ?string $brokerId = null): InstallmentAvailabilityResponse
    {
        $response = $this->send(new BrokerAvailabilityRequest(
            amount: $amount,
            employeeId: $employeeId,
            inn: $inn,
            outletId: $outletId,
            phone: $phone,
            brokerId: $brokerId,
        ));

        /** @var InstallmentAvailabilityResponse $dto */
        $dto = $response->data;

        return $dto;
    }

    /**
     * Execute request with signing and normalized response mapping.
     *
     * @throws PayloadValidationException
     * @throws ApiResponseException
     * @throws TransportException
     * @return MonoPartsResponse
     */
    private function send(MonoPartsRequest $request): MonoPartsResponse
    {
        try {
            $validatedPayload = $request->validate($request->payload());
        } catch (ValidationException $e) {
            throw PayloadValidationException::fromLaravel($e);
        }

        $this->events->dispatch(new RequestSending($request->endpoint(), $validatedPayload));

        $signature = $this->signer->sign($validatedPayload);
        $headers = $this->defaultHeaders($request, $signature);

        $url = $this->config->baseUrl . $request->endpoint();

        try {
            $response = $this->http->withHeaders($headers)->post($url, $validatedPayload);
        } catch (RequestException $e) {
            throw new TransportException('Failed to call Monobank API: ' . $e->getMessage(), $e);
        } catch (Throwable $e) {
            throw new TransportException('Unexpected transport error: ' . $e->getMessage(), $e);
        }

        $isDuplicateCreate = $request instanceof CreateOrderRequest && $response->status() === 409;

        if (!$response->successful() && !$isDuplicateCreate) {
            $exceptionResponse = ExceptionResponse::fromPayload($response->json());
            throw new ApiResponseException($response->status(), $exceptionResponse);
        }

        $monoResponse = $this->buildResponse($request, $response);
        $this->events->dispatch(new ResponseReceived($monoResponse));

        $this->logger->get()->info('monoparts.request.success', [
            'endpoint' => $request->endpoint(),
            'status_code' => $monoResponse->httpStatus,
            'status' => $monoResponse->status->value,
        ]);

        return $monoResponse;
    }

    /**
     * @return array<string, string>
     */
    private function defaultHeaders(MonoPartsRequest $request, string $signature): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            $this->config->signatureHeader => $signature,
        ];

        if ($request->requiresStoreId()) {
            if ($this->config->storeId === null || $this->config->storeId === '') {
                throw new ConfigurationException('store_id must be configured for this request.');
            }
            $headers[$this->config->storeHeader] = $this->config->storeId;
        }

        if ($request->requiresBrokerId()) {
            $brokerIdHeader = $this->config->headers['broker'] ?? 'broker-id';
            $brokerId = $request->brokerId() ?? $this->config->brokerId;
            if ($brokerId === null || $brokerId === '') {
                throw new ConfigurationException('broker_id must be configured for broker availability checks.');
            }
            $headers[$brokerIdHeader] = $brokerId;
        }

        return array_merge($headers, $request->headers());
    }

    /**
     * @return MonoPartsResponse
     */
    private function buildResponse(MonoPartsRequest $request, Response $response): MonoPartsResponse
    {
        $data = $this->mapResponse($request, $response);
        $status = $this->determineStatus($request, $response, $data);

        return MonoPartsResponse::fromHttp($response, $status, $data);
    }

    /**
     * Map HTTP response payload to a typed DTO for the given request.
     */
    private function mapResponse(MonoPartsRequest $request, Response $response): mixed
    {
        $payload = $response->json();

        return match (true) {
            $request instanceof CheckPaidRequest => CheckPaidResult::fromPayload((array) $payload),
            $request instanceof CreateOrderRequest => CreateOrderResult::fromPayload((array) $payload),
            $request instanceof ConfirmOrderRequest,
            $request instanceof RejectOrderRequest,
            $request instanceof OrderStateRequest => OrderStateInfo::fromPayload(is_array($payload) ? $payload : []),
            $request instanceof OrderDataRequest => OrderShortInfo::fromPayload(is_array($payload) ? $payload : []),
            $request instanceof ReturnOrderRequest => ReturnResponse::fromPayload(is_array($payload) ? $payload : []),
            $request instanceof StoreReportRequest => DailyReport::fromPayload(is_array($payload) ? $payload : []),
            $request instanceof ClientValidateV2Request => ValidateClientResponse::fromPayload(is_array($payload) ? $payload : []),
            $request instanceof BrokerAvailabilityRequest => InstallmentAvailabilityResponse::fromPayload(is_array($payload) ? $payload : []),
            default => $payload,
        };
    }

    private function determineStatus(MonoPartsRequest $request, Response $response, mixed $data): ResponseStatus
    {
        // Domain-aware statuses first.
        if ($data instanceof OrderStateInfo && $data->state !== null) {
            return match ($data->state) {
                \Inkvizitoria\MonoParts\Enums\OrderState::SUCCESS => ResponseStatus::ORDER_SUCCESS,
                \Inkvizitoria\MonoParts\Enums\OrderState::FAIL => ResponseStatus::ORDER_FAIL,
                \Inkvizitoria\MonoParts\Enums\OrderState::IN_PROCESS => ResponseStatus::ORDER_IN_PROCESS,
            };
        }

        if ($data instanceof ReturnResponse) {
            return $data->status === \Inkvizitoria\MonoParts\Enums\ReturnStatus::OK
                ? ResponseStatus::RETURN_OK
                : ResponseStatus::RETURN_ERROR;
        }

        if ($data instanceof CreateOrderResult) {
            return $response->status() === 409 ? ResponseStatus::ORDER_DUPLICATE : ResponseStatus::ORDER_CREATED;
        }

        if ($data instanceof CheckPaidResult) {
            return $data->fullyPaid ? ResponseStatus::CHECK_PAID_YES : ResponseStatus::CHECK_PAID_NO;
        }

        if ($data instanceof InstallmentAvailabilityResponse) {
            return $data->available ? ResponseStatus::AVAILABLE : ResponseStatus::NOT_AVAILABLE;
        }

        if ($data instanceof ValidateClientResponse) {
            return $data->found ? ResponseStatus::CLIENT_FOUND : ResponseStatus::CLIENT_NOT_FOUND;
        }

        // Fallback to HTTP-derived status.
        $status = $response->status();
        if ($status >= 200 && $status < 300) {
            return ResponseStatus::SUCCESS_HTTP;
        }
        if ($status >= 400 && $status < 500) {
            return ResponseStatus::CLIENT_ERROR_HTTP;
        }

        return ResponseStatus::SERVER_ERROR_HTTP;
    }
}
