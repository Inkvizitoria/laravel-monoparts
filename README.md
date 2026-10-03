# Laravel MonoParts

Laravel client for monobank installment payments (Purchase in Parts). The package handles order creation, confirmation, cancellation, refunds, reports, guarantee letters, QR carts and signed order callbacks. It does not store orders or require a database.

The request paths and payloads follow the bank's [API documentation](https://u2-demo-ext.mono.st4g3.com/docs/index.html) and [Swagger schema](https://u2-demo-ext.mono.st4g3.com/v2/api-docs).

## Requirements

| Laravel | PHP |
| --- | --- |
| 12 | 8.2–8.5 |
| 13 | 8.3–8.5 |

Composer installs the required Illuminate components, Laravel's validator and Guzzle. Version 2 does not support Laravel 8–11. See [UPGRADING.md](UPGRADING.md) for changes from v1.

## Installation

```bash
composer require inkvizitoria/laravel-monoparts:^2.0
php artisan vendor:publish --tag=monoparts-config
```

Laravel discovers the service provider and facade automatically. Publishing creates `config/monoparts.php`.

Set the credentials supplied by the bank:

```dotenv
MONOPARTS_ENV=production
MONOPARTS_STORE_ID=your-store-id
MONOPARTS_SIGNATURE_SECRET=your-signing-secret
```

Use `MONOPARTS_BROKER_ID` when calling the broker availability endpoint. Credentials are resolved when the client or callback handler is first used; the package can be installed before credentials are configured.

After changing a cached configuration, rebuild it:

```bash
php artisan config:cache
```

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
| `callbacks.path` | `MONOPARTS_CALLBACK_PATH` | `/monoparts/callback` |
| `logging.channel` | `MONOPARTS_LOG_CHANNEL` | `monoparts` |
| `logging.fallback_channel` | `MONOPARTS_FALLBACK_CHANNEL` | `stack` |
| `logging.channel_config.level` | `MONOPARTS_LOG_LEVEL` | `info` |

`environment` accepts `sandbox`, `stage` or `production`. The bundled non-production hosts are:

- Sandbox: `https://u2-demo-ext.mono.st4g3.com`
- Stage: `https://u2-ext.mono.st4g3.com`

Change non-production URLs in `base_urls`. URLs must use HTTPS and cannot contain credentials, query strings or fragments. Timeouts must be positive integer seconds.

The client sends each request once. It does not automatically retry writes. After a timeout, a request may already have reached the bank. Keep the same `store_order_id` when reconciling or repeating an order creation, and keep the same `store_return_id` for a refund operation.

## Create an order

Use the facade or inject `Inkvizitoria\MonoParts\Http\MonoPartsClient` into your service:

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$order = MonoParts::createOrder([
    'store_order_id' => 'ORDER-1001',
    'client_phone' => '+380500000001',
    'total_sum' => '1234.56',
    'invoice' => [
        'date' => '2026-10-03',
        'number' => 'INV-1001',
        'source' => 'INTERNET',
    ],
    'available_programs' => [
        [
            'available_parts_count' => [3, 6],
            'type' => 'payment_installments',
        ],
    ],
    'products' => [
        ['name' => 'Display', 'count' => 1, 'sum' => '1234.56'],
    ],
    'result_callback' => route('monoparts.callback'),
]);

$bankOrderId = $order->orderId;
$state = MonoParts::orderState($bankOrderId);
```

Persist your local order identifier and the bank's `orderId` in your application. HTTP 201 means the application was accepted for processing, not that payment succeeded. HTTP 409 from order creation returns the existing order ID; its normalized status is `ORDER_DUPLICATE`.

For stores that require delivery confirmation, call `confirmOrder()` when the goods are issued. `rejectOrder()` cancels an order before delivery. Use `returnOrder()` for a full or partial refund after delivery. The bank determines whether an operation is permitted for the current order state.

### Create payload validation

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

### Refunds

```php
$result = MonoParts::returnOrder(
    orderId: $bankOrderId,
    sum: '100.00',
    returnMoneyToCard: true,
    storeReturnId: 'REFUND-1001',
    additionalParams: ['nds' => '0.00'],
);

if ($result->status === \Inkvizitoria\MonoParts\Enums\ReturnStatus::OK) {
    // Record the accepted refund in your application.
}
```

`$sum` accepts `Money|int|float|string`. `returnMoneyToCard` is a boolean and `storeReturnId` is a non-empty string. Optional refund parameters currently include `nds` only. `checkPaid()` returns `fullyPaid` and `bankCanReturnMoneyToCard`.

### Reports and client eligibility

```php
$report = MonoParts::storeReport('2026-10-02');
foreach ($report->orders as $entry) {
    $transferred = $entry->transferredSum?->toDecimal();
}

$client = MonoParts::validateClientV2('+380500000001');
$isClient = $client->found;

$availability = MonoParts::brokerAvailability(
    amount: '1000.00',
    employeeId: 'employee-1',
    inn: '1234567890',
    outletId: 'outlet-1',
    phone: '+380500000001',
);
```

Report dates use `Y-m-d`. Broker calls use `broker-id` instead of `store-id`; the optional method argument overrides the configured broker ID. The client-validation phone is optional in the bank's schema; an omitted phone produces an empty JSON object.

### Guarantee letters

```php
$data = MonoParts::guaranteeLetterDataV2($bankOrderId, [
    'date' => '2026-10-03',
    'number' => 'INV-1001',
]);

$header = $data->header;
$expansion = $data->expansion;
$originalPayload = $data->raw;

$pdf = MonoParts::guaranteeLetter($bankOrderId);
return response($pdf, 200)->header('Content-Type', 'application/pdf');
```

The invoice override is optional and accepts `date` and `number`. Guarantee data retains the bank's nested document fields as arrays; these amounts are not converted to `Money`. The PDF method returns the original binary body and rejects a successful response without a PDF header marker.

### QR carts

```php
$cart = MonoParts::createQrCart([
    'qr_id' => 'your-store-qr-id',
    'store_order_id' => '123e4567-e89b-12d3-a456-426614174000',
    'products' => [
        ['name' => 'Display', 'count' => 1, 'sum' => '1234.56'],
    ],
    'result_callback' => 'https://shop.example/qr-callback',
]);

$cartId = $cart->id;
MonoParts::cancelQrCart('your-store-qr-id');
```

A QR cart requires a UUID `store_order_id` and a callback URL. Product validation follows the price, count and name rules used for orders. Cancellation uses the store's `qr_id`, not the returned cart ID. The bundled callback processor validates order-state payloads; handle any other callback schema in your application.

## Signing

The default signer uses the shared secret issued by the bank:

```php
$signature = base64_encode(hash_hmac('sha256', $rawJsonBody, $secret, true));
```

Requests carry this value in the `signature` header. Callback verification always uses the original request body and a constant-time comparison. Do not decode and re-encode a callback before checking its signature.

To require signatures on HTTP responses, set `MONOPARTS_VERIFY_RESPONSE_SIGNATURE=true`. A missing or invalid response signature then throws `SignatureValidationException`, including for error responses. This option does not control callback verification; callbacks are always verified.

To replace signing, bind `Inkvizitoria\MonoParts\Contracts\SignerInterface` in your application's service provider before resolving the client. Implement `sign()`, `verify()` and `assertValid()`. `assertValid()` must throw `SignatureValidationException` or a subclass for a rejected signature. A custom binding takes precedence over the default signer; changing the driver setting alone does not create a custom implementation.

## Order callbacks

The provider registers `POST /monoparts/callback`, named `monoparts.callback`. It uses the `api` middleware group and returns:

| Status | Meaning |
| --- | --- |
| 200 | Signature and payload validated; synchronous listeners completed |
| 400 | Signed body is not valid JSON or fails payload validation |
| 403 | Signature is missing or invalid |
| 500 | Processing or a synchronous event listener failed |

A callback requires a UUID `order_id` and a `state` of `SUCCESS`, `FAIL` or `IN_PROCESS`. `order_sub_state` and `message` are optional strings. Unknown sub-states remain available as `rawOrderSubState`; recognized values also map to `OrderSubState`.

Listen to `CallbackValidated` for application updates:

```php
use Illuminate\Support\Facades\Event;
use Inkvizitoria\MonoParts\Events\CallbackValidated;

Event::listen(CallbackValidated::class, function (CallbackValidated $event): void {
    $bankOrderId = $event->stateInfo->orderId;
    $state = $event->stateInfo->state;
    // Apply the state to your application's stored order.
});
```

Make application updates idempotent. The package does not deduplicate callbacks or persist their state. A queued listener runs after the HTTP acknowledgment; manage its failures through your queue. If a synchronous validated listener throws, the callback returns 500.

Configure `callbacks.enabled`, `callbacks.path` and `callbacks.middleware` in the published config. Set `enabled` to `false` to register your own route and inject `CallbackHandlerInterface`. If you use the `web` middleware group, exempt this callback path from CSRF validation. Keep the configured route publicly reachable at the URL sent in `result_callback`.

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

## License and author

MIT. See [LICENSE](LICENSE).

Maintained by [Denis Drozh](https://github.com/Inkvizitoria). Report reproducible issues at [GitHub Issues](https://github.com/Inkvizitoria/laravel-monoparts/issues).
