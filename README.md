# Laravel MonoParts

Laravel client for monobank Purchase in Parts (Покупка частинами). The package validates and signs requests, returns typed responses and provides a signed callback endpoint. It supports order creation, status checks, delivery confirmation, cancellation, refunds, reports, guarantee letters, QR carts and client/broker checks.

This cookbook explains how to use those operations in an existing Laravel application. No migrations, Eloquent models or application services are required by the package.

**Requirements:** Laravel 12 with PHP 8.2+, or Laravel 13 with PHP 8.3+. For migration from version 1, see [UPGRADING.md](UPGRADING.md). Exact signatures, validation rules, DTO fields and configuration options are in the [reference](docs/reference.md).

## Contents

1. [Install and connect](#1-install-and-connect)
2. [Choose a client interface](#2-choose-a-client-interface)
3. [Understand the payment flow](#3-understand-the-payment-flow)
4. [Create an order](#4-create-an-order)
5. [Receive callbacks and read state](#5-receive-callbacks-and-read-state)
6. [Confirm delivery or cancel](#6-confirm-delivery-or-cancel)
7. [Make a full or partial refund](#7-make-a-full-or-partial-refund)
8. [Read order details and daily reports](#8-read-order-details-and-daily-reports)
9. [Get guarantee letters](#9-get-guarantee-letters)
10. [Create and cancel QR carts](#10-create-and-cancel-qr-carts)
11. [Check clients and broker availability](#11-check-clients-and-broker-availability)
12. [Handle errors and inspect events](#12-handle-errors-and-inspect-events)
13. [Test the integration](#13-test-the-integration)
14. [Use production credentials](#14-use-production-credentials)

## 1. Install and connect

Run in your Laravel project:

```bash
composer require inkvizitoria/laravel-monoparts:^2.0
```

Laravel discovers the service provider and facade automatically. Configure the credentials issued for your environment in `.env`:

```dotenv
MONOPARTS_ENV=sandbox
MONOPARTS_STORE_ID=your-sandbox-store-id
MONOPARTS_SIGNATURE_SECRET=your-sandbox-signing-secret
```

`MONOPARTS_ENV` accepts `sandbox`, `stage` or `production`; the default is `production`. Sandbox credentials do not select the sandbox automatically.

| Environment | Default host |
| --- | --- |
| `sandbox` | `https://u2-demo-ext.mono.st4g3.com` |
| `stage` | `https://u2-ext.mono.st4g3.com` |
| `production` | `https://u2.monobank.com.ua` |

Publish configuration if you need to change callback settings, hosts, headers, signing or logging:

```bash
php artisan vendor:publish --tag=monoparts-config
```

This creates `config/monoparts.php`. Existing configuration is preserved unless you pass `--force`. Publishing is optional for the default setup.

The package registers `POST /monoparts/callback`, named `monoparts.callback`, with the `api` middleware group. Check it with:

```bash
php artisan route:list --name=monoparts.callback
```

For callbacks, your application needs a public HTTPS address. Configure `APP_URL` and your proxy/HTTPS settings so `route('monoparts.callback')` generates that address. During local development, use a public HTTPS tunnel or another reachable development host. The bank cannot call localhost. The callback uses signature verification; browser session authentication and CSRF middleware must not block it.

## 2. Choose a client interface

The examples use the facade:

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$state = MonoParts::orderState('123e4567-e89b-12d3-a456-426614174000');
```

The container provides the same client for dependency injection or direct resolution:

```php
use Inkvizitoria\MonoParts\Http\MonoPartsClient;

$client = app(MonoPartsClient::class);
$state = $client->orderState('123e4567-e89b-12d3-a456-426614174000');
```

Use either interface in your existing controller, service, command or job. The package handles credentials, HTTP transport, validation and signing in both cases. You do not need to construct requests, calculate signatures or use a separate HTTP client for the operations below.

Amounts are in UAH. Prefer decimal strings such as `'1234.56'`. Product `sum` is the unit price; `count` is the quantity. Integer `100` means UAH 100.00, not 100 kopiykas. To work with kopiykas explicitly:

```php
use Inkvizitoria\MonoParts\ValueObjects\Money;

$amount = Money::fromCents(123456);
$amount->toDecimal(); // '1234.56'
$amount->toCents();   // 123456
```

The client accepts `Money`, integers, floats and decimal strings for monetary fields. It rejects extra decimal precision rather than silently rounding. It sends amounts as JSON numbers and signs exactly the bytes sent. See [monetary limits](docs/reference.md#monetary-values).

## 3. Understand the payment flow

The usual flow is:

1. Call `createOrder()` when the customer chooses installments at checkout.
2. Receive a bank `orderId`. The application is now being processed; it is not yet proof of payment completion.
3. Receive signed callbacks, or call `orderState()` to check progress while the customer approves the purchase in the bank app.
4. If your merchant flow requires store confirmation, wait for `WAITING_FOR_STORE_CONFIRM`, issue the goods and call `confirmOrder()`.
5. Use `rejectOrder()` for cancellation before delivery, or `returnOrder()` for a return after delivery.
6. Use `orderData()` for order details and recorded returns, and `storeReport()` for financial reconciliation.

Some merchants do not require store confirmation. Follow the flow enabled for your merchant agreement; do not add a confirmation call to every successful callback.

Keep these identifiers distinct:

| Identifier | Supplied by | Use |
| --- | --- | --- |
| `store_order_id` | Your application | Identify one order-creation attempt; reuse it when recovering the same attempt |
| `orderId` / bank `order_id` | Bank | Address state, confirmation, cancellation, refunds and documents |
| `storeReturnId` / `store_return_id` | Your application | Identify one return operation; a separate return gets a separate ID |
| QR `id` | Bank | Identify the created cart; it is not automatically an installment order ID |
| `qr_id` | Bank/merchant QR setup | Select the store QR for cart creation and cancellation |

Retain the bank order ID with your existing order/payment data. The package returns IDs but does not store them. A timeout may occur after the bank has acted: retain the original merchant ID and payload for recovery instead of creating a new payment attempt automatically.

## 4. Create an order

Call `createOrder()` with an associative array. The example creates a UAH 1,234.56 purchase with a choice of three or six installments:

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$payload = [
    'store_order_id' => 'ORDER-1001-ATTEMPT-1',
    'client_phone' => '+380500000001',
    'total_sum' => '1234.56',
    'invoice' => [
        'date' => '2026-10-03',
        'number' => 'INV-1001',
        'source' => 'INTERNET',
    ],
    'available_programs' => [
        ['type' => 'payment_installments', 'available_parts_count' => [3, 6]],
    ],
    'products' => [
        ['name' => 'Display', 'count' => 1, 'sum' => '1234.56'],
    ],
    'result_callback' => route('monoparts.callback'),
];

$created = MonoParts::createOrder($payload);
$bankOrderId = $created->orderId;
```

`CreateOrderResult::orderId` is a bank UUID. Use that value for subsequent order operations. The examples below use `123e4567-e89b-12d3-a456-426614174000` as a sample bank ID; replace it with the returned value.

Build prices and product information from your existing checkout data on the server. Use the part counts agreed with the bank. `invoice.source` is `INTERNET`, `STORE` or `CHECKOUT`, according to the purchase channel. Phone numbers must use `+380` followed by nine digits. Arrays for products, programs and part counts must be non-empty lists.

`result_callback` is optional in the API payload. Supply it when using the package's callback route. The route receives the bank result; it is not a URL that the customer opens after checkout. Show your own pending-payment screen while waiting for the result.

### Optional order fields

Add only the fields your merchant integration uses:

```php
$payload['invoice']['point_id'] = 'outlet-1';
$payload['additional_params'] = [
    'nds' => '0.00',
    'ext_initial_sum' => '100.00',
    'seller_phone' => '+380500000002',
];
$payload['financial_company_merchant_info'] = [
    'edrpou_code' => '12345678',
    'iban_account' => 'UA123456789012345678901234567',
    'store_name' => 'Example Store',
];
```

This block modifies the payload **before** calling `createOrder()`. `nds` is VAT and `ext_initial_sum` is an initial payment. Confirm their use and the merchant details with the bank; local validation does not determine eligibility or bank accounting rules. All supported fields and limits are listed in the [payload reference](docs/reference.md#create-order-payload).

### Duplicate creation and timeouts

For a duplicate create, the bank can return HTTP 409 with the existing `order_id`. The package maps that response to a normal `CreateOrderResult`; it does not require catching a conflict exception.

After a create timeout, deliberately resubmit the **same saved payload and `store_order_id`** to recover the existing bank ID. Do not generate a new ID, change prices or change invoice data for that recovery. A new customer application after an established rejection is a separate attempt and should have a separate merchant ID.

The duplicate normalization applies only to `createOrder()`. It does not establish a general retry guarantee for refunds, confirmations or QR writes.

## 5. Receive callbacks and read state

The package verifies the original callback body signature, validates the payload and dispatches `CallbackValidated`. Use this event to connect the result to your existing order handling.

Register a listener in the `boot()` method of your existing `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Event;
use Inkvizitoria\MonoParts\Events\CallbackValidated;

Event::listen(CallbackValidated::class, function (CallbackValidated $event): void {
    $bankOrderId = $event->stateInfo?->orderId;
    $state = $event->stateInfo?->state;
    $subState = $event->stateInfo?->orderSubState;
    $message = $event->stateInfo?->message;

    // Pass these values to your existing order/payment handling.
});
```

The event also exposes `$event->payload` with the validated bank field names and `$event->signature`. `state` and `orderSubState` are enums; their string values are available through `->value`. Unknown sub-states remain available as `stateInfo->rawOrderSubState`.

The endpoint responds with HTTP 200 after validation and synchronous listeners complete. A missing or invalid signature gets 403; invalid JSON or payload gets 400; a processing/listener failure gets 500. The callback route and validation are supplied by the package; you do not need another callback controller.

Keep callback handling short. Record the result in your existing application flow before returning successfully, and make repeated notifications harmless. If you delegate work to a queue, configure that queue in your application and retain enough information to recover failed dispatch or processing. The HTTP acknowledgement is not proof that a queued job has completed.

A callback can arrive before your create-response handling finishes. Match callbacks by the bank order ID and account for this timing. Do not issue goods again on duplicate notifications or regress a completed payment because an older pending notification arrives.

### Query the current state

Use `orderState()` when you need the latest bank state, missed a callback or are investigating a pending operation:

```php
use Inkvizitoria\MonoParts\Enums\OrderState;
use Inkvizitoria\MonoParts\Enums\OrderSubState;
use Inkvizitoria\MonoParts\Facades\MonoParts;

$bankOrderId = '123e4567-e89b-12d3-a456-426614174000';
$state = MonoParts::orderState($bankOrderId);

$isCompleted = $state->state === OrderState::SUCCESS;
$isDeclined = $state->state === OrderState::FAIL;
$isWaitingForStore = $state->state === OrderState::IN_PROCESS
    && $state->orderSubState === OrderSubState::WAITING_FOR_STORE_CONFIRM;
$bankMessage = $state->message;
```

Callbacks and state queries use the same `OrderStateInfo` DTO:

| State | Meaning | Typical action |
| --- | --- | --- |
| `IN_PROCESS` / `WAITING_FOR_CLIENT` | Customer approval is pending | Wait for a callback or check state later |
| `IN_PROCESS` / `WAITING_FOR_STORE_CONFIRM` | Store confirmation is required | Issue goods through your fulfillment flow, then confirm |
| `SUCCESS` | Bank application completed successfully | Record completion in your existing payment flow |
| `FAIL` | Bank application rejected or cancelled | Read the sub-state/message and handle the rejection |
| `state === null` in an HTTP response | Bank returned an unrecognized high-level state | Inspect `rawState`; reconcile before fulfillment |

`SUCCESS` does not mean the customer has repaid every installment or that a settlement transfer has arrived. Use `checkPaid()` and `storeReport()` for those separate questions.

Polling makes HTTP calls to the bank. Use bounded intervals and backoff in your existing background processing rather than repeatedly calling the bank from a tight loop. When local and bank terminal states conflict, investigate instead of overwriting the result automatically.

## 6. Confirm delivery or cancel

### Confirm goods were issued

Use `confirmOrder()` in a merchant flow that requires store confirmation. Check the current state before issuing goods; send confirmation after your application has recorded issuance:

```php
use Inkvizitoria\MonoParts\Enums\OrderState;
use Inkvizitoria\MonoParts\Enums\OrderSubState;
use Inkvizitoria\MonoParts\Facades\MonoParts;

$bankOrderId = '123e4567-e89b-12d3-a456-426614174000';
$current = MonoParts::orderState($bankOrderId);

if ($current->state !== OrderState::IN_PROCESS
    || $current->orderSubState !== OrderSubState::WAITING_FOR_STORE_CONFIRM) {
    throw new \LogicException('Order is not waiting for store confirmation.');
}

// Issue the goods and record issuance in your existing fulfillment flow first.
$confirmed = MonoParts::confirmOrder($bankOrderId);
$bankState = $confirmed->state;
```

The result is an `OrderStateInfo`, not a boolean. Inspect its state; an HTTP 200 does not by itself establish a terminal result.

If confirmation times out after issuance, query `orderState()` before deciding whether to repeat confirmation. The warehouse action and the bank call are separate operations: a failed HTTP call must not cause the goods to be handed over twice.

### Cancel before delivery

Call `rejectOrder()` when abandoning an application before delivery:

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$bankOrderId = '123e4567-e89b-12d3-a456-426614174000';
$cancelled = MonoParts::rejectOrder($bankOrderId);
$bankState = $cancelled->state;
$reason = $cancelled->message;
```

The bank determines whether cancellation is allowed in the current state. Coordinate this action with your existing fulfillment process so cancellation and issuance cannot run concurrently. After delivery, use a refund rather than pre-delivery cancellation.

## 7. Make a full or partial refund

Use `returnOrder()` after delivery when returning goods. The amount determines whether the return is full or partial; there is no separate full-refund method.

### Check repayment and card-return availability

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$bankOrderId = '123e4567-e89b-12d3-a456-426614174000';
$paid = MonoParts::checkPaid($bankOrderId);

$fullyRepaid = $paid->fullyPaid;
$cardReturnAvailable = $paid->bankCanReturnMoneyToCard;
```

`fullyPaid` refers to the customer's repayment of the installment application. `bankCanReturnMoneyToCard` tells you whether a bank card return is available. These flags answer different questions.

### Submit a card refund

The example returns UAH 100.00 to the customer's card:

```php
use Inkvizitoria\MonoParts\Enums\ReturnStatus;
use Inkvizitoria\MonoParts\Facades\MonoParts;

$bankOrderId = '123e4567-e89b-12d3-a456-426614174000';
$refundId = 'RETURN-1001-1';
$paid = MonoParts::checkPaid($bankOrderId);

if (!$paid->bankCanReturnMoneyToCard) {
    throw new \LogicException('The bank cannot return this amount to the card.');
}

$return = MonoParts::returnOrder(
    orderId: $bankOrderId,
    sum: '100.00',
    returnMoneyToCard: true,
    storeReturnId: $refundId,
);

$accepted = $return->status === ReturnStatus::OK;
$rawStatus = $return->rawStatus;
```

Use the stable ID of this return from your existing return/refund workflow. A second, separate partial return needs its own ID. A full return uses the remaining refundable amount, accounting for previous returns and unresolved operations; the package does not calculate this balance.

`returnMoneyToCard=false` has a specific bank meaning: **the store has already returned cash to the customer**. It is not an automatic fallback when a card return is unavailable. Use it only after the corresponding refund procedure has been completed and recorded.

To supply VAT for the return, pass `additionalParams: ['nds' => '0.00']`. `nds` is the only supported additional refund field. `sum` also accepts `Money::fromCents(...)` if your existing refund amount is stored in kopiykas.

`ReturnStatus::OK` records API acceptance, not proof that a credit has appeared on the card. `ERROR` and unrecognized return statuses require checking the result; unknown strings remain in `rawStatus`.

After a timeout or ambiguous result, do not create a new return ID and send another refund automatically. The package has no refund-status lookup. Use recorded reversals from `orderData()`, reports and the bank's recovery procedure. Reversals contain amount and timestamp, not the merchant refund ID; two equal amounts cannot be distinguished by amount alone.

## 8. Read order details and daily reports

### Read invoice data and recorded returns

Use `orderData()` for the order's details. It complements `orderState()` rather than replacing the workflow-state query:

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$bankOrderId = '123e4567-e89b-12d3-a456-426614174000';
$details = MonoParts::orderData($bankOrderId);

$merchantOrderId = $details->storeOrderId;
$invoiceNumber = $details->invoiceNumber;
$total = $details->totalSum?->toDecimal();

foreach ($details->reverseList as $reverse) {
    $returnedAmount = $reverse->sum?->toDecimal();
    $returnedAt = $reverse->timestamp?->format(DATE_ATOM);
}
```

Response amounts are `Money|null`. Missing or unparseable amounts mean unknown, not zero. Date/time properties are `DateTimeImmutable|null`. The [DTO reference](docs/reference.md#order-details) lists all fields, including invoice, merchant source, IBAN and masked card data.

### Read a daily report

Use `storeReport()` to reconcile financial operations for a bank reporting date in `Y-m-d` format:

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$report = MonoParts::storeReport('2026-10-02');

foreach ($report->orders as $entry) {
    $bankOrderId = $entry->orderId;
    $transactionId = $entry->transactionId;
    $grossAmount = $entry->totalSum?->toDecimal();
    $commission = $entry->commission?->toDecimal();
    $transferredAmount = $entry->transferredSum?->toDecimal();
}
```

`DailyReport::orders` is a list of `DailyReportOrder` objects. An order can have multiple financial operations; do not collapse every row with the same order ID into one settlement. Transaction IDs and other fields may be absent. Choose the reporting cutoff and import identity with your bank/accounting integration. The [report field reference](docs/reference.md#daily-report-rows) covers all returned fields.

## 9. Get guarantee letters

Use guarantee-letter methods when your merchant workflow needs the bank's document or accounting data for an existing application. Document availability depends on the bank state and merchant agreement.

### Structured data

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$bankOrderId = '123e4567-e89b-12d3-a456-426614174000';
$data = MonoParts::guaranteeLetterDataV2($bankOrderId);

$header = $data->header;
$expansion = $data->expansion;
$raw = $data->raw;
```

These properties preserve the bank's nested arrays. Their monetary fields are not automatically converted to `Money`. For an integration requiring the original data endpoint, use `MonoParts::guaranteeLetterData($bankOrderId)` with the same arguments.

Both data methods and the PDF method accept an optional invoice override:

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$bankOrderId = '123e4567-e89b-12d3-a456-426614174000';
$data = MonoParts::guaranteeLetterData($bankOrderId, [
    'date' => '2026-10-03',
    'number' => 'INV-1001',
]);
```

Supply your actual issued invoice details. Omit the second argument when no override is required.

### PDF bytes

In an existing authorized download action:

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$bankOrderId = '123e4567-e89b-12d3-a456-426614174000';
$pdf = MonoParts::guaranteeLetter($bankOrderId);

return response($pdf, 200, [
    'Content-Type' => 'application/pdf',
    'Content-Disposition' => 'attachment; filename="guarantee-letter.pdf"',
    'Cache-Control' => 'private, no-store',
]);
```

The return value is the original PDF string, not a DTO or base64 value. Authorize the customer's access to the order before downloading. If archiving, use your existing private storage. The package rejects a successful response that does not begin with a PDF marker.

## 10. Create and cancel QR carts

QR carts are a separate bank checkout flow. Use these methods if your merchant setup provides a store QR identifier. Cart creation does not itself establish a completed installment payment.

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$qrId = 'your-store-qr-id';
$cart = MonoParts::createQrCart([
    'qr_id' => $qrId,
    'store_order_id' => '123e4567-e89b-12d3-a456-426614174001',
    'products' => [
        ['name' => 'Display', 'count' => 1, 'sum' => '1234.56'],
    ],
    'result_callback' => 'https://checkout.example.com/qr-callback',
]);

$bankCartId = $cart->id;
```

Unlike the ordinary create request, QR `store_order_id` must be a UUID, and `result_callback` is required. Retain the merchant ID, QR ID and returned cart ID with your existing checkout information. Do not substitute the cart ID for a bank order ID in order-state methods.

Confirm the QR callback schema with the bank. The bundled callback endpoint validates the order-state contract; it handles a QR result only if that result follows the same contract. A different QR schema needs your existing application endpoint to verify the original body using the package's `SignerInterface` and validate the agreed fields. There is no generic QR callback parser in this package.

Cancel an abandoned basket by the **store QR ID**, not the returned cart ID:

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

MonoParts::cancelQrCart('your-store-qr-id');
```

Cancellation returns `void` after a successful HTTP response. Coordinate operations on a reused store QR so an old cancellation does not cancel a new basket. The package has no cart-state lookup or duplicate-QR normalization; use the bank's QR recovery procedure after an uncertain write.

## 11. Check clients and broker availability

### Look up a client

Run a client lookup before creation when your checkout needs to know whether the phone belongs to a bank client:

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$client = MonoParts::validateClientV2('+380500000001');
$found = $client->found;
```

`found=false` is a normal lookup result. `true` does not approve a purchase or reserve credit. The phone argument is optional in the bank schema, but supply it for a customer-specific lookup.

### Check broker availability

This operation requires a broker integration and its employee/outlet identifiers. Set the broker ID issued to you:

```dotenv
MONOPARTS_BROKER_ID=your-broker-id
```

Then query availability for the basket:

```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$result = MonoParts::brokerAvailability(
    amount: '1234.56',
    employeeId: 'employee-1',
    inn: '1234567890',
    outletId: 'outlet-1',
    phone: '+380500000001',
);

$available = $result->available;
```

`inn` is the customer's tax identifier. Use identifiers from your actual broker setup. The method uses the `broker-id` header instead of `store-id`. An optional `brokerId` argument overrides the configured broker ID for this call; choose it from trusted server configuration.

`available=false` is a business result, not an HTTP failure. `true` still does not replace order creation and customer approval. Keep tax IDs and phones out of routine logs.

## 12. Handle errors and inspect events

Local payload errors throw `PayloadValidationException` before the HTTP request. At your existing checkout boundary, inspect the field errors:

```php
use Inkvizitoria\MonoParts\Exceptions\PayloadValidationException;
use Inkvizitoria\MonoParts\Facades\MonoParts;

try {
    $created = MonoParts::createOrder($payload);
} catch (PayloadValidationException $exception) {
    $fieldErrors = $exception->errors();
    // Translate these field errors through your existing validation handling.
    throw $exception;
}
```

This example uses the payload from [order creation](#4-create-an-order). The package exception is not Laravel's `ValidationException`; adapt it to your existing HTTP or console error handling.

| Failure | Available information | How to interpret it |
| --- | --- | --- |
| `ConfigurationException` | Exception message | Settings or required credentials are invalid |
| `PayloadValidationException` | `errors()` by field | Local request or signed callback validation failed |
| `ApiResponseException` | `statusCode`, `exceptionResponse->message` | Bank returned a non-success HTTP response |
| `TransportException` | Exception message and previous exception where available | Network failed, or response shape/content was invalid |
| `SignatureValidationException` | Exception message | A required signature could not be trusted |

All are in `Inkvizitoria\MonoParts\Exceptions` and extend `MonoPartsException`. An HTTP 500, timeout, invalid response or listener exception after sending a write may leave the remote operation completed. Reconcile before replaying. The client performs no automatic retries. Read requests can use bounded retry/backoff in your existing flow; write recovery depends on the operation.

Successful HTTP responses can carry negative business results: `found=false`, `available=false`, `ReturnStatus::ERROR` or `OrderState::FAIL`. Check the DTO rather than equating HTTP success with an approved purchase.

### Inspect response metadata

Public methods return their DTOs directly. To observe the HTTP status and normalized result, register `ResponseReceived` in your existing service provider:

```php
use Illuminate\Support\Facades\Event;
use Inkvizitoria\MonoParts\Events\ResponseReceived;

Event::listen(ResponseReceived::class, function (ResponseReceived $event): void {
    $httpStatus = $event->response->httpStatus;
    $status = $event->response->status;
    $data = $event->response->data;

    // Feed metadata to your existing monitoring without logging customer payloads.
});
```

`ResponseStatus` distinguishes creation, duplicate creation, state and other operation results. `successful()` describes the normalized result; `ORDER_IN_PROCESS` counts as successful processing but does not mean settlement. Unknown high-level states produce `ORDER_UNKNOWN`, which is not successful.

Other events are `RequestSending`, `CallbackReceived`, `CallbackValidated` and `CallbackFailed`. Only `CallbackValidated` establishes a valid callback. Event payloads and contracts are in the [event reference](docs/reference.md#events-and-response-metadata).

The default `monoparts` log records request endpoints and response statuses in `storage/logs/monoparts.log`. It does not log outgoing bodies, signing secrets or PDF bytes. Your listeners receive customer data; choose what they record. Configure the channel in `config/monoparts.php` if needed.

## 13. Test the integration

Use Laravel's `Http::fake()` to exercise package calls without contacting the bank. In an existing Laravel feature test:

```php
use Illuminate\Support\Facades\Http;
use Inkvizitoria\MonoParts\Facades\MonoParts;

config([
    'monoparts.environment' => 'sandbox',
    'monoparts.merchant.store_id' => 'test-store',
    'monoparts.merchant.signature_secret' => 'test-secret',
]);

Http::preventStrayRequests();
Http::fake([
    'https://u2-demo-ext.mono.st4g3.com/api/order/create' => Http::response([
        'order_id' => '123e4567-e89b-12d3-a456-426614174000',
    ], 201),
]);

// Use the payload from the create-order recipe.
$created = MonoParts::createOrder($payload);
$this->assertSame('123e4567-e89b-12d3-a456-426614174000', $created->orderId);
Http::assertSentCount(1);
Http::assertSent(fn ($request) => $request->hasHeader('store-id', 'test-store')
    && $request->hasHeader('signature')
    && $request['store_order_id'] === $payload['store_order_id']);
```

Set test configuration before resolving the client. Verify your existing order handling with positive, negative and duplicate responses, and with failures after a write. Faking HTTP does not exercise the bank or public network.

### Send a signed callback in a feature test

The signature must match the exact bytes passed to the endpoint:

```php
use Illuminate\Support\Facades\Event;
use Inkvizitoria\MonoParts\Events\CallbackValidated;
use Inkvizitoria\MonoParts\Security\Signer;

config(['monoparts.merchant.signature_secret' => 'test-secret']);
Event::fake([CallbackValidated::class]);

$body = json_encode([
    'order_id' => '123e4567-e89b-12d3-a456-426614174000',
    'state' => 'SUCCESS',
    'order_sub_state' => 'SUCCESS',
], JSON_THROW_ON_ERROR);
$signature = (new Signer('test-secret'))->sign($body);

$this->call('POST', '/monoparts/callback', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_SIGNATURE' => $signature,
], $body)->assertOk();

Event::assertDispatched(CallbackValidated::class, fn ($event) =>
    $event->stateInfo?->orderId === '123e4567-e89b-12d3-a456-426614174000');
```

`Event::fake()` above verifies package dispatch; it prevents the selected event's application listeners from running. Test your listener separately without faking that event. Also cover invalid signatures, duplicate callbacks and callback/create-response timing.

### Package development and sandbox tests

The package repository's own test suite, compatibility matrix and opt-in sandbox tests are documented in the [testing reference](docs/reference.md#testing). Those commands run from a source checkout; installed distribution archives exclude the package tests.

Use the bank's sandbox identities and merchant scenarios for end-to-end acceptance. Verify that the bank can reach your callback URL and that your existing order handling receives the event. Sandbox writes can leave applications in the sandbox; do not run them against production credentials.

## 14. Use production credentials

Once the sandbox flow works, configure the credentials issued for production:

```dotenv
MONOPARTS_ENV=production
MONOPARTS_STORE_ID=your-production-store-id
MONOPARTS_SIGNATURE_SECRET=your-production-signing-secret
MONOPARTS_TIMEOUT=30
MONOPARTS_CONNECT_TIMEOUT=10
```

Rebuild Laravel configuration and route caches according to your deployment process:

```bash
php artisan config:cache
php artisan route:cache
```

Restart long-running workers if your existing integration uses them. Verify the callback address after deployment. Outstanding orders retain the callback URL sent at creation; a hostname/path change must account for those orders.

Requests are always signed, and callbacks are always signature-verified. Set `MONOPARTS_VERIFY_RESPONSE_SIGNATURE=true` to additionally require signatures on bank HTTP responses **when your bank integration provides them**. Missing signatures then fail, including on error responses. This flag does not control callback verification.

The default signer uses HMAC-SHA256 with the shared secret. A custom implementation can be bound through `SignerInterface`; see [signing](docs/reference.md#signing). Keep credentials server-side and use the environment matching those credentials.

## Sources and maintenance

Bank contracts: [API documentation](https://u2-demo-ext.mono.st4g3.com/docs/index.html) and [Swagger schema](https://u2-demo-ext.mono.st4g3.com/v2/api-docs). This cookbook describes the package's interfaces and integration considerations; it does not prescribe an application database or fulfillment architecture.

MIT. See [LICENSE](LICENSE). Maintained by [Denis Drozh](https://github.com/Inkvizitoria). Report reproducible package issues at [GitHub Issues](https://github.com/Inkvizitoria/laravel-monoparts/issues).
