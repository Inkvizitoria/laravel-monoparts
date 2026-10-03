# API and configuration reference

Use the [cookbook](../README.md) for operation examples and the payment flow. This page documents package contracts, DTOs, configuration and validation limits.

## Contents

- [Configuration](#configuration)
- [Create-order payload](#create-order-payload) and [monetary values](#monetary-values)
- [API methods](#api-methods) and [additional argument contracts](#additional-argument-contracts)
- [Signing](#signing) and [callback contract](#callback-contract)
- [Events and response metadata](#events-and-response-metadata)
- [Exceptions](#exceptions), [logging](#logging) and [testing](#testing)
- [DTO fields](#dto-fields) and [states and enums](#states-and-enums)

## Configuration

| Config key | Environment variable | Default |
| --- | --- | --- |
| `environment` | `MONOPARTS_ENV` | `production` |
| `production_url` | `MONOPARTS_PROD_URL` | `https://u2.monobank.com.ua` |
| `merchant.store_id` | `MONOPARTS_STORE_ID` | Empty |
| `merchant.signature_secret` | `MONOPARTS_SIGNATURE_SECRET` | Empty |
| `merchant.broker_id` | `MONOPARTS_BROKER_ID` | Empty |
| `http.timeout` | `MONOPARTS_TIMEOUT` | 30 seconds |
| `http.connect_timeout` | `MONOPARTS_CONNECT_TIMEOUT` | 10 seconds |
| `signature.driver` | `MONOPARTS_SIGNATURE_DRIVER` | `hmac` |
| `signature.algo` | `MONOPARTS_SIGNATURE_ALGO` | `sha256` |
| `signature.header` | `MONOPARTS_SIGNATURE_HEADER` | `signature` |
| `verify_response_signature` | `MONOPARTS_VERIFY_RESPONSE_SIGNATURE` | `false` |
| `headers.store` | `MONOPARTS_STORE_ID_HEADER` | `store-id` |
| `headers.broker` | `MONOPARTS_BROKER_ID_HEADER` | `broker-id` |
| `callbacks.enabled` | Config only | `true` |
| `callbacks.middleware` | Config only | `['api']` |
| `callbacks.path` | `MONOPARTS_CALLBACK_PATH` | `/monoparts/callback` |
| `logging.channel` | `MONOPARTS_LOG_CHANNEL` | `monoparts` |
| `logging.fallback_channel` | `MONOPARTS_FALLBACK_CHANNEL` | `stack` |
| `logging.channel_config.driver` | Config only | `single` |
| `logging.channel_config.path` | Config only | `storage_path('logs/monoparts.log')` |
| `logging.channel_config.level` | `MONOPARTS_LOG_LEVEL` | `info` |

`environment` accepts `sandbox`, `stage` or `production`. The bundled non-production hosts are:

- Sandbox: `https://u2-demo-ext.mono.st4g3.com`
- Stage: `https://u2-ext.mono.st4g3.com`

Set `callbacks.enabled=false` in the published configuration to omit the bundled callback route when your application provides its own verified callback handler. Changing `callbacks.middleware` changes the route middleware; it does not bypass signature verification.

Change non-production URLs in `base_urls`. URLs must use HTTPS and cannot contain credentials, query strings or fragments. Timeouts must be positive integer seconds.

The client sends each request once. It does not automatically retry writes. After a timeout, a request may already have reached the bank. Keep the same `store_order_id` when reconciling or repeating an order creation, and keep the same `store_return_id` for a refund operation.

## Create-order payload

| Field | Rule |
| --- | --- |
| `store_order_id` | Required string, 1–64 characters |
| `client_phone` | Required `+380` followed by nine digits |
| `total_sum` | Required amount, at least UAH 1.00 |
| `invoice.date` | Required date in `Y-m-d` format |
| `invoice.number` | Required non-empty string |
| `invoice.source` | `STORE`, `INTERNET` or `CHECKOUT` |
| `invoice.point_id` | Optional string, 1–50 characters |
| `available_programs` | Required non-empty list |
| `available_programs[].type` | `payment_installments` |
| `available_programs[].available_parts_count` | Non-empty list of positive 32-bit integers |
| `products` | Required non-empty list |
| `products[].name` | String, 1–500 characters |
| `products[].count` | Positive 32-bit integer |
| `products[].sum` | Unit price, at least UAH 0.01 |
| `result_callback` | Optional URL |
| `financial_company_merchant_info.edrpou_code` | Optional string of digits |
| `financial_company_merchant_info.iban_account` | Optional `UA` followed by 27 digits |
| `financial_company_merchant_info.store_name` | Optional string |
| `additional_params.nds` | Optional non-negative VAT amount |
| `additional_params.ext_initial_sum` | Optional non-negative initial payment |
| `additional_params.seller_phone` | Optional `+380` followed by nine digits |

Nested objects accept the fields listed above. Monetary values accept at most two decimal places. Local validation checks field types and limits; the bank validates program availability and business rules.

## Monetary values

Request amounts are UAH. Use decimal strings or `Money` for exact amounts:

```php
use Inkvizitoria\MonoParts\ValueObjects\Money;

$amount = Money::fromDecimal('1234.56');
$amount->toCents();   // 123456
$amount->toDecimal(); // '1234.56'

Money::fromCents(100)->toDecimal(); // '1.00'
Money::fromNumber(100)->toDecimal(); // '100.00'
```

Integer inputs mean whole UAH, including numeric strings such as `'100'`. Only `fromCents()` interprets its input as kopiykas. Float inputs remain accepted for compatibility, but values with extra precision are rejected rather than rounded. Floats at or above 2^46 UAH are rejected because they cannot reliably distinguish adjacent cent values; use a decimal string instead. Prefer decimal strings to avoid floating-point arithmetic in payment amounts.

`Money` permits zero for report amounts and optional VAT or initial-payment fields. Required prices, refunds and broker amounts must be at least UAH 0.01; an order total must be at least UAH 1.00. Negative amounts and integer overflow are rejected.

The client serializes monetary request fields as JSON numbers with two fractional digits. It builds the body once, signs those bytes and sends the same bytes. It does not turn monetary values into JSON strings.

Response money fields in `OrderShortInfo`, `ReverseEntry` and `DailyReportOrder` are `Money|null`. Missing or unparseable amounts map to `null`. For example:

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$details = MonoParts::orderData($bankOrderId);
$total = $details->totalSum?->toDecimal();
```

## API methods

All methods use HTTP POST. Order identifiers passed to order methods must be UUID strings.

| Method | Endpoint | Return type |
| --- | --- | --- |
| `createOrder(array $payload)` | `/api/order/create` | `CreateOrderResult` |
| `checkPaid(string $orderId)` | `/api/order/check/paid` | `CheckPaidResult` |
| `confirmOrder(string $orderId)` | `/api/order/confirm` | `OrderStateInfo` |
| `rejectOrder(string $orderId)` | `/api/order/reject` | `OrderStateInfo` |
| `orderState(string $orderId)` | `/api/order/state` | `OrderStateInfo` |
| `orderData(string $orderId)` | `/api/order/data` | `OrderShortInfo` |
| `returnOrder($orderId, $sum, $returnMoneyToCard, $storeReturnId, $additionalParams = [])` | `/api/order/return` | `ReturnResponse` |
| `storeReport(string $date)` | `/api/store/report` | `DailyReport` |
| `validateClientV2(?string $phone = null)` | `/api/v2/client/validate` | `ValidateClientResponse` |
| `brokerAvailability($amount, $employeeId, $inn, $outletId, $phone, $brokerId = null)` | `/api/fin/broker/check/installment/availability` | `InstallmentAvailabilityResponse` |
| `guaranteeLetterData($orderId, $invoice = [])` | `/api/order/data/for/guarantee/letter` | `GuaranteeLetterData` |
| `guaranteeLetterDataV2($orderId, $invoice = [])` | `/api/v2/order/data/for/guarantee/letter` | `GuaranteeLetterData` |
| `guaranteeLetter($orderId, $invoice = [])` | `/api/order/guarantee/letter` | PDF bytes as `string` |
| `createQrCart(array $payload)` | `/api/v1/qr/cart` | `QrCartResult` |
| `cancelQrCart(string $qrId)` | `/api/v1/qr/cart/cancel` | `void` |

DTO classes are in `Inkvizitoria\MonoParts\Http\Responses`. The deprecated `/api/order/info` endpoint is not exposed; use `orderData()`.

## Signing

The default signer uses the shared secret issued by the bank:

```php
$signature = base64_encode(hash_hmac('sha256', $rawJsonBody, $secret, true));
```

Requests carry this value in the `signature` header. Callback verification always uses the original request body and a constant-time comparison. Do not decode and re-encode a callback before checking its signature.

To require signatures on HTTP responses, set `MONOPARTS_VERIFY_RESPONSE_SIGNATURE=true`. A missing or invalid response signature then throws `SignatureValidationException`, including for error responses. This option does not control callback verification; callbacks are always verified.

To replace signing, bind `Inkvizitoria\MonoParts\Contracts\SignerInterface` in your application's service provider before resolving the client. Implement `sign()`, `verify()` and `assertValid()`. `assertValid()` must throw `SignatureValidationException` or a subclass for a rejected signature. A custom binding takes precedence over the default signer; changing the driver setting alone does not create a custom implementation.

## Callback contract

The provider registers `POST /monoparts/callback`, named `monoparts.callback`. It uses the `api` middleware group and returns:

| Status | Meaning |
| --- | --- |
| 200 | Signature and payload validated; synchronous listeners completed |
| 400 | Signed body is not valid JSON or fails payload validation |
| 403 | Signature is missing or invalid |
| 500 | Processing or a synchronous event listener failed |

A callback requires a UUID `order_id` and a `state` of `SUCCESS`, `FAIL` or `IN_PROCESS`. `order_sub_state` and `message` are optional strings. Unknown sub-states remain available as `rawOrderSubState`; recognized values also map to `OrderSubState`.

## Events and response metadata

| Event | Data |
| --- | --- |
| `RequestSending` | Endpoint and validated request payload; money fields are `Money` objects |
| `ResponseReceived` | `MonoPartsResponse` with normalized status, HTTP status, mapped data, raw JSON and headers |
| `CallbackReceived` | Decoded payload after signature verification, or an empty payload on a signature/decoding failure; emitted once |
| `CallbackValidated` | Validated payload, signature and `OrderStateInfo` |
| `CallbackFailed` | Available payload, signature and exception |

Only `CallbackValidated` represents a validated callback. Do not treat `CallbackReceived` as authorization to update an order.

Public client methods return their DTOs directly. Use `ResponseReceived` to observe `ResponseStatus` or HTTP metadata. PDF responses have `raw = null` and their bytes in `data`; QR cancellation has `data = null`.

`ResponseStatus` distinguishes order creation, duplicates, order states, refund results, paid flags, availability and client lookup. Unknown high-level order states are preserved in `rawState` and produce `ORDER_UNKNOWN`, which is not a successful result. `MonoPartsResponse::successful()` describes the normalized result; `ORDER_IN_PROCESS` means processing has started, not that funds have settled.

## Exceptions

All package exceptions extend `Inkvizitoria\MonoParts\Exceptions\MonoPartsException`:

| Exception | Cause |
| --- | --- |
| `ConfigurationException` | Missing credentials or identifiers, invalid environment, URL, timeout, signature driver or HMAC algorithm |
| `PayloadValidationException` | Outgoing payload or signed callback failed validation; `errors()` returns errors grouped by field |
| `ApiResponseException` | Non-success HTTP response, except an order-creation duplicate; exposes `statusCode` and `exceptionResponse` |
| `TransportException` | Network failure, malformed successful JSON, wrong response field types, missing result ID or invalid PDF |
| `SignatureValidationException` | Missing or invalid response/callback signature |
| `InvalidSignatureException` | Invalid signature reported by the default signer; extends `SignatureValidationException` |

Non-JSON API errors retain their HTTP status and use a generic message. Errors from application event listeners are not wrapped as API errors.

## Logging

The default `monoparts` channel writes request endpoint, HTTP status and normalized status to `storage/logs/monoparts.log`. Callback rejection and validation failures log their reason; unexpected callback failures log the exception. The package does not log outgoing bodies, signing secrets or PDF bytes.

An existing application channel named `monoparts` is preserved. You can change `logging.channel` or `logging.channel_config`. Logger resolution falls back to `logging.fallback_channel`, then a null logger if a channel cannot be resolved. Event payloads contain customer data; control what your own listeners log.

## Testing

Run these commands in a package source checkout. Distribution archives exclude the package tests; installed applications supply their own integration tests.

```bash
composer install
composer validate --strict
composer test
composer audit
```

The default suite uses Testbench and fake HTTP responses. It blocks unfaked requests and excludes the `integration` group. CI resolves dependencies independently for Laravel 12 and 13 and runs the suite on the supported PHP versions.

Sandbox integration tests are opt-in. Set the following environment variables in your shell, then run `composer test:integration`:

```dotenv
MONOPARTS_RUN_INTEGRATION=1
MONOPARTS_TEST_STORE_ID=your-sandbox-store-id
MONOPARTS_TEST_SIGNATURE_SECRET=your-sandbox-secret
MONOPARTS_TEST_PHONE=your-sandbox-test-phone
```

These tests use the fixed sandbox host. Client validation runs when the required variables are present. The create-and-state test also requires `MONOPARTS_TEST_CREATE_ORDER=1`; it creates a sandbox order and leaves that order in the sandbox. The tests are skipped without explicit opt-in and credentials. They do not load a local `.env` file automatically.


## Additional argument contracts

All client methods are available through `Inkvizitoria\MonoParts\Http\MonoPartsClient` or the `Inkvizitoria\MonoParts\Facades\MonoParts` facade. All endpoints use POST. Order ID arguments are bank UUIDs.

| Method | Arguments beyond the bank order ID |
| --- | --- |
| `returnOrder()` | `Money\|int\|float\|string $sum`, `bool $returnMoneyToCard`, non-empty `string $storeReturnId`, `array $additionalParams = []` |
| `storeReport()` | `string $date` in `Y-m-d` format |
| `validateClientV2()` | `?string $phone = null`; when supplied, `+380` followed by nine digits |
| `brokerAvailability()` | `Money\|int\|float\|string $amount`, non-empty `string $employeeId`, `string $inn`, `string $outletId`, Ukrainian `string $phone`, `?string $brokerId = null` |
| `guaranteeLetterData()`, `guaranteeLetterDataV2()`, `guaranteeLetter()` | `array $invoice = []`; allowed fields are `date` (`Y-m-d`) and `number` (non-empty string) |
| `createQrCart()` | `array $payload` with non-empty `qr_id`, UUID `store_order_id`, non-empty `products` list and required URL `result_callback` |
| `cancelQrCart()` | Non-empty `string $qrId`, identifying the store QR rather than the returned cart ID |

For QR products, `name` is a string of 1–500 characters, `count` is a positive 32-bit integer and `sum` is a unit price of at least UAH 0.01. Nested product objects accept these three fields only.

Refund `additionalParams` accepts `nds` as a nullable, non-negative monetary amount. `returnMoneyToCard=false` reports that the store has already returned cash to the customer. It is not a fallback instruction asking the bank to choose a different refund channel.

Client methods return DTOs directly. They do not expose a public generic `send()` method, a refund-status lookup or a QR-cart lookup. Only order creation normalizes HTTP 409 into a duplicate result.

## DTO fields

The following types are in `Inkvizitoria\MonoParts\Http\Responses`. JSON uses the bank's field names; DTO properties use the names shown here.

| DTO | Properties |
| --- | --- |
| `CreateOrderResult` | `string orderId` |
| `CheckPaidResult` | `bool fullyPaid`, `bool bankCanReturnMoneyToCard` |
| `OrderStateInfo` | `?string message`, `?string orderId`, `?OrderState state`, `?OrderSubState orderSubState`, `?string rawState`, `?string rawOrderSubState` |
| `ReturnResponse` | `ReturnStatus status`, `?string rawStatus`; unknown status values normalize to `ERROR` and remain in `rawStatus` |
| `ValidateClientResponse` | `bool found` |
| `InstallmentAvailabilityResponse` | `bool available` |
| `GuaranteeLetterData` | `array header`, `array expansion`, `array raw` |
| `QrCartResult` | `string id` |
| `DailyReport` | `DailyReportOrder[] orders` |
| `ReverseEntry` | `?Money sum`, `?DateTimeImmutable timestamp` |

### Order details

`OrderShortInfo` has these properties:

| Property | Type |
| --- | --- |
| `createTimestamp` | `DateTimeImmutable\|null` |
| `iban`, `invoiceDate`, `invoiceNumber`, `maskedCard`, `pointId` | `string\|null` |
| `reverseList` | `ReverseEntry[]` |
| `source`, `storeOrderId` | `string\|null` |
| `totalSum` | `Money\|null` |

### Daily report rows

`DailyReportOrder` preserves an individual operation:

| Properties | Type |
| --- | --- |
| `commission`, `creditSum`, `sentSum`, `totalSum`, `transferredSum` | `Money\|null` |
| `commissionPercent` | `float\|null` |
| `createDateTime`, `operationTimestamp` | `DateTimeImmutable\|null` |
| `payParts` | `int\|null` |
| `cardNumber`, `invoiceNumber`, `odbContractNumber`, `orderDate`, `orderId`, `paymentDate`, `terminalId`, `transactionDate`, `transactionId` | `string\|null` |

Missing or unparseable monetary fields are `null`, not zero. Supported timestamp parsing accepts ATOM timestamps and `Y-m-d\TH:i:s`; absent or unparseable values map to `null`. Do not infer a bank timezone from a timestamp without an offset.

### Normalized response metadata

`ResponseReceived::response` is an `Inkvizitoria\MonoParts\Http\MonoPartsResponse`:

| Property | Type and meaning |
| --- | --- |
| `status` | `ResponseStatus`, the normalized result |
| `httpStatus` | Integer HTTP status |
| `raw` | Decoded JSON array or `null` for non-JSON bodies |
| `data` | The mapped DTO, PDF bytes, or `null` for QR cancellation |
| `headers` | HTTP response header array |

`successful()` is a helper for the normalized result. `ORDER_IN_PROCESS` counts as successful processing, while `ORDER_UNKNOWN` does not. Neither this helper nor HTTP 200 proves goods were issued, the customer repaid the loan, or a settlement transfer arrived.

### States and enums

Enums are in `Inkvizitoria\MonoParts\Enums`; response metadata statuses are in `Inkvizitoria\MonoParts\Status\ResponseStatus`.

| Enum | Values or behavior |
| --- | --- |
| `Environment` | `SANDBOX`, `STAGE`, `PRODUCTION` |
| `OrderState` | `IN_PROCESS`, `SUCCESS`, `FAIL` |
| `OrderSubState` | Recognized bank details, including `WAITING_FOR_CLIENT`, `WAITING_FOR_STORE_CONFIRM`, `REJECTED_BY_CLIENT`, `REJECTED_BY_STORE`; preserve raw values for future extensions |
| `ReturnStatus` | `OK`, `ERROR`; unknown return strings normalize to `ERROR` |

Callback validation requires a known high-level state but permits an unknown sub-state string. HTTP state responses preserve unknown high-level strings in `rawState`, map `state` to `null` and use normalized status `ORDER_UNKNOWN`.
