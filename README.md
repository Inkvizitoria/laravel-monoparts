# Laravel MonoParts cookbook

Integrate monobank installment payments (Purchase in Parts) into a Laravel application. This guide follows a payment from checkout through customer approval, delivery, cancellation, refunds and financial reconciliation. It also covers guarantee letters, QR carts and broker eligibility checks.

The package provides signed HTTP requests, payload validation, typed responses and a callback endpoint. Your application owns its orders, payment records, inventory, delivery, refund ledger and background workers. The models and migrations below are application examples; the package does not create database tables.

For exact method signatures, DTO fields and settings, use the [reference](docs/reference.md). For an existing v1 integration, start with [UPGRADING.md](UPGRADING.md).

## Contents

1. [Install and configure the connection](#1-install-and-configure-the-connection)
2. [Define the payment records](#2-define-the-payment-records)
3. [Prepare and submit an order](#3-prepare-and-submit-an-order)
4. [Receive callbacks and reconcile state](#4-receive-callbacks-and-reconcile-state)
5. [Issue goods or cancel the purchase](#5-issue-goods-or-cancel-the-purchase)
6. [Process a full or partial refund](#6-process-a-full-or-partial-refund)
7. [Retrieve guarantee letters](#7-retrieve-guarantee-letters)
8. [Reconcile orders and settlements](#8-reconcile-orders-and-settlements)
9. [Use QR carts](#9-use-qr-carts)
10. [Check client and broker eligibility](#10-check-client-and-broker-eligibility)
11. [Handle errors and uncertain operations](#11-handle-errors-and-uncertain-operations)
12. [Test and deploy the integration](#12-test-and-deploy-the-integration)

## 1. Install and configure the connection

Laravel 12 requires PHP 8.2+; Laravel 13 requires PHP 8.3+. Version 2 supports these two Laravel versions.

Run in your Laravel application:

```bash
composer require inkvizitoria/laravel-monoparts:^2.0
php artisan vendor:publish --tag=monoparts-config
```

Laravel discovers the package provider and facade automatically. Publishing creates `config/monoparts.php`; it preserves an existing file unless you pass `--force`.

Start with credentials issued for the sandbox:

```dotenv
APP_URL=https://checkout.example.com
MONOPARTS_ENV=sandbox
MONOPARTS_STORE_ID=your-sandbox-store-id
MONOPARTS_SIGNATURE_SECRET=your-sandbox-signing-secret
MONOPARTS_TIMEOUT=30
MONOPARTS_CONNECT_TIMEOUT=10
```

The callback must be reachable by the bank over HTTPS. For local development, supply a public HTTPS address for your application and generate the callback URL from that address. A localhost URL cannot receive a bank callback.

The default hosts are `https://u2-demo-ext.mono.st4g3.com` for sandbox, `https://u2-ext.mono.st4g3.com` for stage and `https://u2.monobank.com.ua` for production. Select the environment that matches the credentials. Base URLs must use HTTPS. Keep credentials on the server; the browser submits a checkout request to your application, which calls the bank.

Check the installed route:

```bash
php artisan route:list --name=monoparts.callback
```

You should see `POST monoparts/callback`. The route uses Laravel's `api` middleware group. In `bootstrap/app.php`, retain your application's `withMiddleware(...)` configuration so Laravel registers its middleware groups. Keep authentication and browser CSRF middleware off this bank-facing route; the package verifies the bank's signature instead.

The examples use dependency injection for application services and `app(MonoPartsClient::class)` for short action snippets. The `MonoParts` facade exposes the same methods. Choose one style in your application.

## 2. Define the payment records

Keep your commercial order separate from its bank payment attempt. The identifiers have different purposes:

| Identifier | Owner | Purpose |
| --- | --- | --- |
| `order_reference` | Your application | Find the commercial order, for example `ORDER-1001` |
| Payment attempt `id` / `store_order_id` | Your application | Identify one immutable application to the bank; reuse it when recovering that application |
| `bank_order_id` / bank `order_id` | Bank | Address state, delivery, cancellation, refund and document requests |
| Refund `id` / `store_return_id` | Your application | Identify one refund; each separate refund has a separate ID |

A failed customer application can have a new attempt after you establish that the old attempt is finished. A timeout alone does not justify a new attempt: the bank may already have accepted the previous one.

### Create the example tables

Generate a migration in the host application:

```bash
php artisan make:migration create_installment_payment_tables
```

Use this migration body. `order_reference` deliberately has no foreign key: replace it with your application's order relationship when integrating with an existing schema.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('installment_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('order_reference');
            $table->unsignedInteger('attempt_no')->default(1);
            $table->uuid('bank_order_id')->nullable()->unique();
            $table->json('request_payload');
            $table->string('status')->default('prepared');
            $table->string('bank_state')->nullable();
            $table->string('bank_sub_state')->nullable();
            $table->timestamps();
            $table->unique(['order_reference', 'attempt_no']);
        });

        Schema::create('monoparts_notifications', function (Blueprint $table): void {
            $table->id();
            $table->char('fingerprint', 64)->unique();
            $table->uuid('bank_order_id')->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('installment_refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('installment_payments');
            $table->unsignedBigInteger('amount_cents');
            $table->boolean('return_money_to_card');
            $table->string('status')->default('prepared');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_refunds');
        Schema::dropIfExists('monoparts_notifications');
        Schema::dropIfExists('installment_payments');
    }
};
```

Create `app/Models/InstallmentPayment.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class InstallmentPayment extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['request_payload' => 'array'];
}
```

Create `app/Models/MonopartsNotification.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MonopartsNotification extends Model
{
    protected $guarded = [];
    protected $casts = ['payload' => 'array', 'processed_at' => 'datetime'];
}
```

Create `app/Models/InstallmentRefund.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class InstallmentRefund extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['amount_cents' => 'integer', 'return_money_to_card' => 'boolean'];
}
```

These models allow mass assignment so the examples stay focused on the payment workflow. Only pass server-built attributes to them. Never call `create($request->all())` or expose unrestricted updates to these records.

```bash
php artisan migrate
```

The unique order/attempt index prevents concurrent checkout requests from inserting the same logical attempt twice. The unique bank ID prevents one bank payment from being attached to two local attempts. The notification table provides a durable inbox for callbacks, including callbacks that arrive before the create response has been saved.

## 3. Prepare and submit an order

Run this step when the customer selects installments and submits checkout. Authenticate the customer, verify ownership of the commercial order, check stock and calculate prices from your catalog before preparing the bank request. Do not accept totals or unit prices directly from the browser.

### Freeze the request before contacting the bank

The example purchases one display for UAH 1,234.56. Amount strings are UAH; `1234.56` means 123,456 kopiykas. Product `sum` is a unit price, and `count` is the quantity. The supported part counts come from your merchant agreement, not an arbitrary customer value.

In your checkout application service, prepare the attempt:

```php
use App\Models\InstallmentPayment;
use Illuminate\Support\Str;

$candidateId = (string) Str::uuid();

$payment = InstallmentPayment::firstOrCreate(
    ['order_reference' => 'ORDER-1001', 'attempt_no' => 1],
    [
        'id' => $candidateId,
        'request_payload' => [
            'store_order_id' => $candidateId,
            'client_phone' => '+380500000001',
            'total_sum' => '1234.56',
            'invoice' => [
                'date' => '2026-10-03',
                'number' => 'INV-1001',
                'source' => 'INTERNET',
            ],
            'available_programs' => [
                ['available_parts_count' => [3, 6], 'type' => 'payment_installments'],
            ],
            'products' => [
                ['name' => 'Display', 'count' => 1, 'sum' => '1234.56'],
            ],
            'result_callback' => route('monoparts.callback'),
        ],
    ],
);
```

`firstOrCreate()` returns the existing attempt on a repeated checkout submission. The new candidate ID and payload are used only when inserting a record. Submit the returned record's saved payload, so a retry cannot change the original amount, invoice or callback address.

Store decimal strings in the JSON snapshot. Although the client accepts `Money` instances, storing those objects directly through an Eloquent JSON cast does not produce an amount string.

### Call the bank and save its ID

Create `app/Services/SubmitInstallmentPayment.php`:

```php
<?php

namespace App\Services;

use App\Models\InstallmentPayment;
use Illuminate\Support\Facades\Cache;
use Inkvizitoria\MonoParts\Exceptions\ConfigurationException;
use Inkvizitoria\MonoParts\Exceptions\PayloadValidationException;
use Inkvizitoria\MonoParts\Http\MonoPartsClient;
use Throwable;

final class SubmitInstallmentPayment
{
    public function __construct(private readonly MonoPartsClient $mono)
    {
    }

    public function submit(string $paymentId): InstallmentPayment
    {
        return Cache::lock('monoparts:create:' . $paymentId, 90)->block(5, function () use ($paymentId) {
            $payment = InstallmentPayment::findOrFail($paymentId);
            if ($payment->bank_order_id !== null) {
                return $payment;
            }

            try {
                $result = $this->mono->createOrder($payment->request_payload);
            } catch (ConfigurationException|PayloadValidationException $e) {
                $payment->update(['status' => 'create_failed']);
                throw $e;
            } catch (Throwable $e) {
                $payment->update(['status' => 'create_uncertain']);
                throw $e;
            }

            $payment->update([
                'bank_order_id' => $result->orderId,
                'status' => 'submitted',
            ]);

            return $payment->refresh();
        });
    }
}
```

Call it with the prepared record:

```php
use App\Services\SubmitInstallmentPayment;

$payment = app(SubmitInstallmentPayment::class)->submit($payment->id);
$bankOrderId = $payment->bank_order_id;
```

Use a shared cache backend that supports atomic locks across your application instances. The 90-second lease exceeds the default 30-second HTTP timeout; adjust both together if you increase the timeout. The lock limits concurrent submission; the saved merchant ID and bank duplicate detection remain necessary if a process dies or a lease expires. Keep the bank call outside a database transaction so network latency does not hold row locks.

The returned ID means the bank accepted the application for processing. The customer still needs to approve it in the bank app. Show a pending-payment screen and continue with state processing below. Do not issue goods based on the create response.

On an order-creation duplicate, the bank can return HTTP 409 and the existing `order_id`; the package returns a normal `CreateOrderResult`. If a create request times out, recover by deliberately resubmitting the same saved payload and `store_order_id`, then save the returned ID. Do not generate a replacement ID or assume the first call failed.

## 4. Receive callbacks and reconcile state

A successful create request starts an asynchronous process. The bank sends an order-state callback to the saved `result_callback`. Your application must turn that notification into a durable local state update.

### Define one state applicator

Both callback processing and scheduled polling should use the same transition rules. Create `app/Services/ApplyInstallmentState.php`:

```php
<?php

namespace App\Services;

use App\Models\InstallmentPayment;
use Illuminate\Support\Facades\DB;
use Inkvizitoria\MonoParts\Enums\OrderState;
use Inkvizitoria\MonoParts\Enums\OrderSubState;
use Inkvizitoria\MonoParts\Http\Responses\OrderStateInfo;
use LogicException;

final class ApplyInstallmentState
{
    public function apply(OrderStateInfo $info): InstallmentPayment
    {
        return DB::transaction(function () use ($info) {
            $payment = InstallmentPayment::where('bank_order_id', $info->orderId)
                ->lockForUpdate()->firstOrFail();

            $terminal = in_array($payment->bank_state, ['SUCCESS', 'FAIL'], true);
            if ($terminal && $info->state === OrderState::IN_PROCESS) {
                return $payment;
            }
            if ($terminal && ($info->state === null || $payment->bank_state !== $info->state->value)) {
                throw new LogicException('Conflicting terminal bank states require reconciliation.');
            }

            $status = match ($info->state) {
                OrderState::SUCCESS => 'completed',
                OrderState::FAIL => 'declined',
                OrderState::IN_PROCESS => $info->orderSubState === OrderSubState::WAITING_FOR_STORE_CONFIRM
                    ? 'ready_for_delivery' : 'pending',
                null => 'review',
            };

            $payment->update([
                'status' => $status,
                'bank_state' => $info->rawState,
                'bank_sub_state' => $info->rawOrderSubState,
            ]);

            return $payment->refresh();
        });
    }
}
```

`completed` here means the installment application reached bank state `SUCCESS`. It does not mean the customer has repaid all installments or that a bank transfer has reached your account. Those are separate checks in the refund and reconciliation recipes.

The applicator preserves unknown raw states for investigation, prevents a delayed pending snapshot from replacing a terminal state, and refuses contradictory terminal states. Refund progress belongs in the refund ledger; do not erase a completed payment record after a partial return.

### Save verified callbacks before acknowledging them

Listen to `CallbackValidated`, which the package emits after checking the original body signature and validating its fields. Do not update orders from `CallbackReceived` or from a browser payment-result page.

Create `app/Listeners/CaptureMonopartsCallback.php`:

```php
<?php

namespace App\Listeners;

use App\Jobs\ProcessMonopartsNotification;
use App\Models\MonopartsNotification;
use Inkvizitoria\MonoParts\Events\CallbackValidated;

final class CaptureMonopartsCallback
{
    public function handle(CallbackValidated $event): void
    {
        $payload = [
            'order_id' => $event->payload['order_id'],
            'state' => $event->payload['state'],
            'order_sub_state' => $event->payload['order_sub_state'] ?? null,
            'message' => $event->payload['message'] ?? null,
        ];
        $fingerprint = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $notification = MonopartsNotification::firstOrCreate(
            ['fingerprint' => $fingerprint],
            ['bank_order_id' => $payload['order_id'], 'payload' => $payload],
        );

        if ($notification->processed_at === null) {
            ProcessMonopartsNotification::dispatch($notification->id)
                ->onQueue('payments')->afterCommit();
        }
    }
}
```

The listener is synchronous: its database write completes before the package returns HTTP 200. Do not add `ShouldQueue` to this inbox-capture listener. If persistence fails, let the exception reach the callback processor, which returns HTTP 500. If dispatch fails after saving, the inbox record remains available for recovery.

Add this explicit registration to your existing `AppServiceProvider::boot()` in `app/Providers/AppServiceProvider.php`:

```php
\Illuminate\Support\Facades\Event::listen(
    \Inkvizitoria\MonoParts\Events\CallbackValidated::class,
    \App\Listeners\CaptureMonopartsCallback::class,
);
```

For this explicit-registration example, create the listener manually and prevent Laravel from also discovering it. In `bootstrap/app.php`, use `->withEvents(discover: false)` on the existing application builder. If your application uses event discovery for other listeners, keep discovery and omit the explicit registration instead. Check `php artisan event:list` to ensure this listener appears once.

### Process the inbox asynchronously

Create `app/Jobs/ProcessMonopartsNotification.php`:

```php
<?php

namespace App\Jobs;

use App\Models\InstallmentPayment;
use App\Models\MonopartsNotification;
use App\Services\ApplyInstallmentState;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Inkvizitoria\MonoParts\Http\MonoPartsClient;

final class ProcessMonopartsNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;
    public int $timeout = 45;

    public function __construct(public readonly int $notificationId)
    {
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function middleware(): array
    {
        $bankId = MonopartsNotification::findOrFail($this->notificationId)->bank_order_id;

        return [(new WithoutOverlapping('monoparts:state:' . $bankId))
            ->shared()->releaseAfter(10)->expireAfter(60)];
    }

    public function handle(MonoPartsClient $mono, ApplyInstallmentState $states): void
    {
        $notification = MonopartsNotification::findOrFail($this->notificationId);
        if ($notification->processed_at !== null) {
            return;
        }
        if (!InstallmentPayment::where('bank_order_id', $notification->bank_order_id)->exists()) {
            $this->release(10);
            return;
        }

        $current = $mono->orderState($notification->bank_order_id);
        $states->apply($current);
        $notification->update(['processed_at' => now()]);
    }
}
```

This recipe treats the callback as a notification to fetch the bank's current state. It adds a state request, but avoids applying an old callback as if it were the latest snapshot. The per-bank-order lock serializes inbox jobs; terminal-state guards also protect against slower polling requests. If your integration applies the callback payload directly instead, retain the same transition guards and a recovery path for out-of-order notifications.

A callback can arrive before `bank_order_id` has been saved locally. The inbox captures it, and the job waits for the mapping. Unknown orders remain unprocessed for investigation instead of disappearing. If a job exhausts its attempts, its inbox row still exists.

Configure an asynchronous queue connection such as database or Redis. With the `sync` connection, the job runs in the callback request and the intended asynchronous separation is lost. Start a worker for this queue:

```bash
php artisan queue:work --queue=payments --timeout=45 --tries=10
```

Set the connection's `retry_after` above the worker timeout, for example 90 seconds for this recipe. The job timeout must exceed the configured bank request timeout. Supervise the worker in production. See Laravel's [queue transaction handling](https://laravel.com/docs/12.x/queues#jobs-and-database-transactions) and [worker timeout guidance](https://laravel.com/docs/12.x/queues#job-expirations-and-timeouts).

Emails, inventory changes and delivery actions must be idempotent too. For external side effects, write an application outbox entry in the same database transaction as the state change, then deliver it independently with a unique operation key. A database transaction cannot undo an email or a warehouse API call.

### Interpret the next action

| Observed bank state | Meaning for this integration | Next action |
| --- | --- | --- |
| `IN_PROCESS` / `WAITING_FOR_CLIENT` | Customer approval is still pending | Keep the checkout pending; wait or poll |
| `IN_PROCESS` / `WAITING_FOR_STORE_CONFIRM` | Store confirmation is required | Issue goods through your authorized fulfillment process, then confirm |
| `SUCCESS` | Application completed successfully | Record completion; avoid duplicate fulfillment |
| `FAIL` | Application was rejected or cancelled | Record the reason; release reservations according to your order policy |
| Unknown state or conflicting terminal result | Automatic processing cannot determine a safe transition | Hold fulfillment and reconcile with the bank |

A merchant that does not use store confirmation can reach `SUCCESS` without calling `confirmOrder()`. Use the flow enabled for your merchant agreement. Do not automatically call confirmation whenever any callback arrives.

## 5. Issue goods or cancel the purchase

Delivery and cancellation are explicit business actions. Call these methods from an authorized fulfillment service or staff action, using the stored bank ID. A callback updates payment state; it does not establish that the warehouse actually handed over goods.

### Confirm issuance

For a merchant using store confirmation, verify that the latest bank state is `WAITING_FOR_STORE_CONFIRM`, perform your controlled issuance step and send confirmation:

```php
use App\Services\ApplyInstallmentState;
use Inkvizitoria\MonoParts\Enums\OrderSubState;
use Inkvizitoria\MonoParts\Http\MonoPartsClient;

$mono = app(MonoPartsClient::class);
$current = $mono->orderState($payment->bank_order_id);

if ($current->orderSubState !== OrderSubState::WAITING_FOR_STORE_CONFIRM) {
    throw new \LogicException('This payment is not waiting for store confirmation.');
}

// Call confirmOrder after your application records that goods were issued.
$confirmed = $mono->confirmOrder($payment->bank_order_id);
app(ApplyInstallmentState::class)->apply($confirmed);
```

Protect the local issuance action with a per-order lock and an idempotent fulfillment record. If confirmation times out after goods were issued, leave the issuance record intact and mark confirmation as unresolved. Query `orderState()` before deciding whether another confirmation request is needed. Do not release the goods a second time because a network call failed.

The API result is still an `OrderStateInfo`; inspect the state rather than treating every HTTP 200 as final success. The applicator also handles a response that remains `IN_PROCESS`.

### Cancel before delivery

When the purchase is abandoned or your store cannot issue the goods, cancel the bank application before delivery:

```php
use App\Services\ApplyInstallmentState;
use Inkvizitoria\MonoParts\Http\MonoPartsClient;

$cancelled = app(MonoPartsClient::class)->rejectOrder($payment->bank_order_id);
app(ApplyInstallmentState::class)->apply($cancelled);
```

Check your own issuance record before calling this action and serialize it with fulfillment. The bank decides whether cancellation is allowed for the current state. After delivery, use the refund recipe instead. A customer declining the bank application can also produce `FAIL` without a store cancellation call.

## 6. Process a full or partial refund

Use `returnOrder()` after delivery when the goods are returned. A full refund uses the remaining refundable amount; a partial refund uses the amount of the returned goods. Each refund operation gets its own stable merchant refund ID.

### Decide who returns the money

Check the bank's flags before choosing the refund channel:

```php
use Inkvizitoria\MonoParts\Http\MonoPartsClient;

$mono = app(MonoPartsClient::class);
$paid = $mono->checkPaid($payment->bank_order_id);

$customerHasRepaidAllInstallments = $paid->fullyPaid;
$bankCanReturnToCard = $paid->bankCanReturnMoneyToCard;
```

`fullyPaid` describes whether the customer has repaid the installment application. It is not the same as the `SUCCESS` state from order processing.

For `returnMoneyToCard=true`, the bank is asked to return money to the customer's card. For `false`, the bank's contract says the store has already returned cash to the customer. If the bank cannot return money to the card, do not silently switch the flag to `false`: arrange and record the permitted refund procedure first. See the bank's [refund request schema](https://u2-demo-ext.mono.st4g3.com/v2/api-docs).

### Reserve the amount in your local ledger

In an authorized refund service, accept an existing server-issued refund operation ID when resuming a refund. Generate a new ID only for a new return of goods. The following example reserves UAH 100.00 for a new card refund:

```php
use App\Models\InstallmentPayment;
use App\Models\InstallmentRefund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inkvizitoria\MonoParts\ValueObjects\Money;

$refundId = (string) Str::uuid();
$amount = Money::fromDecimal('100.00');
$returnMoneyToCard = true;

if ($returnMoneyToCard && !$bankCanReturnToCard) {
    throw new \LogicException('A card refund is unavailable; reconcile the refund channel first.');
}

$refund = DB::transaction(function () use ($payment, $refundId, $amount, $returnMoneyToCard) {
    $locked = InstallmentPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
    if ($locked->bank_order_id === null || $locked->bank_state !== 'SUCCESS' || $amount->toCents() < 1) {
        throw new \LogicException('A completed bank application and a positive refund amount are required.');
    }

    $reservedCents = (int) InstallmentRefund::where('payment_id', $locked->id)
        ->whereIn('status', ['prepared', 'accepted', 'uncertain'])
        ->sum('amount_cents');
    $totalCents = Money::fromNumber($locked->request_payload['total_sum'])->toCents();
    if ($amount->toCents() > $totalCents - $reservedCents) {
        throw new \LogicException('Refund amount exceeds the unreserved payment amount.');
    }

    return InstallmentRefund::create([
        'id' => $refundId,
        'payment_id' => $locked->id,
        'amount_cents' => $amount->toCents(),
        'return_money_to_card' => $returnMoneyToCard,
    ]);
});
```

The locked payment row serializes reservations from your application. Pending and uncertain refunds reserve money too; otherwise a second operator could refund an amount that is already being processed. The bank remains the authority for the amount actually returnable. If you also perform refunds outside this application, reconcile those returns before trusting the local balance.

### Resume an existing reservation

Load the operation issued by your server, within the payment the current user is authorized to refund. Reuse its amount and channel; do not generate another ID or reserve the amount again:

```php
use App\Models\InstallmentRefund;

// Authorize this payment before resolving a submitted refund operation ID.
$refund = InstallmentRefund::where('payment_id', $payment->id)
    ->whereKey($existingRefundId)->firstOrFail();

if ($refund->status !== 'prepared') {
    // Accepted or uncertain operations go to reconciliation, not another submission.
    throw new \LogicException('This refund has already been submitted; reconcile its result.');
}
```

The submission below reads the saved amount and channel. It checks the status again under the refund lock because another worker may have submitted the same operation after it was loaded.

### Submit the reserved refund once

Use the saved ledger record, then inspect the business status:

```php
use Illuminate\Support\Facades\Cache;
use Inkvizitoria\MonoParts\Enums\ReturnStatus;
use Inkvizitoria\MonoParts\ValueObjects\Money;

Cache::lock('monoparts:refund:' . $refund->id, 90)->block(5, function () use ($refund, $payment, $mono) {
    $refund->refresh();
    if ($refund->status !== 'prepared') {
        return;
    }

    $refund->update(['status' => 'uncertain']);
    $result = $mono->returnOrder(
        orderId: $payment->bank_order_id,
        sum: Money::fromCents($refund->amount_cents),
        returnMoneyToCard: $refund->return_money_to_card,
        storeReturnId: $refund->id,
    );

    $refund->update([
        'status' => $result->status === ReturnStatus::OK ? 'accepted' : 'uncertain',
    ]);
});
```

Marking the record uncertain before transport also covers a process crash after sending. A thrown exception leaves that reservation intact. A non-`OK` or unknown return status requires investigation; the example does not release the reservation based on an unfamiliar response.

`OK` records API acceptance, not proof that a card credit has appeared. Store the refund ID and amount with your return-of-goods record. If needed for your merchant integration, pass `additionalParams: ['nds' => '0.00']`; this is the only supported additional refund parameter.

The package has no refund-status method. For an uncertain operation, inspect `orderData()->reverseList`, reports and the bank's operational information. A reverse entry contains an amount and timestamp, not the merchant refund ID; matching by amount alone cannot prove which of two equal refunds succeeded. Preserve the same refund ID if the bank's reconciliation procedure calls for replay. Never turn an unresolved refund into a new operation with a new ID.

## 7. Retrieve guarantee letters

Use guarantee documents when your merchant workflow requires the bank's contract or accounting data for an existing application. Fetch them after the application has reached the state permitted by your agreement. A missing or temporarily unavailable document does not by itself prove that the payment failed.

Retrieve structured document data:

```php
use Inkvizitoria\MonoParts\Http\MonoPartsClient;

$mono = app(MonoPartsClient::class);
$data = $mono->guaranteeLetterDataV2($payment->bank_order_id, [
    'date' => '2026-10-03',
    'number' => 'INV-1001',
]);

$bankDocumentHeader = $data->header;
$bankDocumentExpansion = $data->expansion;
$fullDocumentData = $data->raw;
```

The invoice override is optional. Use your actual issued invoice, not values sent by the browser. For integrations requiring the original data endpoint, call `guaranteeLetterData()` with the same arguments. The DTO retains the bank's nested schema as arrays; those numeric fields are not automatically converted into `Money`.

Download the PDF as original bytes:

```php
$pdf = $mono->guaranteeLetter($payment->bank_order_id);

return response($pdf, 200, [
    'Content-Type' => 'application/pdf',
    'Content-Disposition' => 'attachment; filename="guarantee-letter.pdf"',
    'Cache-Control' => 'private, no-store',
]);
```

Put this code in an authenticated controller action and authorize access to the commercial order before fetching the document. For archival, write the bytes to a private Laravel storage disk and save its path against the payment. Do not JSON-encode the PDF, put it in logs or publish it on a public disk. The package rejects a successful response that does not begin with a PDF marker.

## 8. Reconcile orders and settlements

Callbacks are the normal notification path. Reconciliation handles lost notifications, dispatch failures, unknown bank IDs, process crashes and local state that remains pending too long. Use scheduled background jobs with bounded batches and backoff; do not poll the bank in a tight browser loop.

### Recover inbox messages

In a scheduled application command, redispatch unprocessed notifications:

```php
use App\Jobs\ProcessMonopartsNotification;
use App\Models\MonopartsNotification;

MonopartsNotification::whereNull('processed_at')->orderBy('id')
    ->chunkById(100, function ($notifications): void {
        foreach ($notifications as $notification) {
            ProcessMonopartsNotification::dispatch($notification->id)->onQueue('payments');
        }
    });
```

For a busy installation, add a dispatch lease or pending-job marker to avoid repeatedly enqueuing the same unresolved inbox record. The job's lock and processed check protect processing, but do not replace queue admission control. Monitor old unprocessed notifications and failed jobs instead of redispatching them indefinitely.

### Refresh a known payment

For a selected local payment whose bank ID is known:

```php
use App\Services\ApplyInstallmentState;
use Inkvizitoria\MonoParts\Http\MonoPartsClient;

$current = app(MonoPartsClient::class)->orderState($payment->bank_order_id);
app(ApplyInstallmentState::class)->apply($current);
```

Use `orderState()` for workflow state. Use `orderData()` when you need invoice information, merchant order reference, amount or recorded reversals:

```php
$details = app(MonoPartsClient::class)->orderData($payment->bank_order_id);
$total = $details->totalSum?->toDecimal();

foreach ($details->reverseList as $reverse) {
    $returnedAmount = $reverse->sum?->toDecimal();
    $returnedAt = $reverse->timestamp;
}
```

An absent or unparseable response amount maps to `null`; it does not mean zero. Do not replace it with `0` in financial reconciliation. If the local bank ID is missing after a create timeout, first recover it using the saved create request as described in step 3; order-state methods take a bank UUID, not your commercial order number.

### Reconcile daily transfers

Use `storeReport()` for financial reconciliation, independently of fulfillment state:

```php
$report = app(MonoPartsClient::class)->storeReport('2026-10-02');

foreach ($report->orders as $entry) {
    $bankOrderId = $entry->orderId;
    $transactionId = $entry->transactionId;
    $grossAmount = $entry->totalSum?->toDecimal();
    $commission = $entry->commission?->toDecimal();
    $transferredAmount = $entry->transferredSum?->toDecimal();
}
```

The date is a bank reporting date in `Y-m-d` format. Decide the reporting cutoff and timezone with your bank/accounting workflow. Preserve individual operations rather than overwriting one amount on the payment record. An order can have more than one operation.

Match rows to the stored bank ID, retain transaction IDs when present and record unmatched rows for investigation. Use a bank transaction identifier or a documented composite key when importing the report twice. Do not assume every nullable `transactionId` is populated or use `orderId` alone as a unique settlement key.

Schedule your application's inbox/state/report commands in `routes/console.php`, use `withoutOverlapping()` for long tasks and `onOneServer()` with a shared lock-capable cache when running multiple schedulers. Run Laravel's scheduler from cron or `schedule:work`. The package does not install reconciliation commands. See [Laravel scheduling](https://laravel.com/docs/12.x/scheduling).

## 9. Use QR carts

A QR cart is a separate checkout entry point for a store QR code. Create it when your merchant QR flow has a priced basket ready for the customer. Confirm with the bank which callback contract and order linkage your QR integration receives; do not infer that the returned cart ID is an installment `order_id`.

Persist the store QR ID, a UUID merchant order ID and the priced payload before submission, using the same snapshot principle as the order recipe:

```php
use Illuminate\Support\Str;
use Inkvizitoria\MonoParts\Http\MonoPartsClient;

$qrId = 'your-store-qr-id';
$qrStoreOrderId = (string) Str::uuid();
$qrPayload = [
    'qr_id' => $qrId,
    'store_order_id' => $qrStoreOrderId,
    'products' => [
        ['name' => 'Display', 'count' => 1, 'sum' => '1234.56'],
    ],
    'result_callback' => 'https://checkout.example.com/qr-callback',
];

$mono = app(MonoPartsClient::class);
$cart = $mono->createQrCart($qrPayload);
$bankCartId = $cart->id;
```

The example assumes you have saved `$qrId`, `$qrStoreOrderId` and `$qrPayload` in your application's QR checkout record before the bank call; save `$bankCartId` when the response arrives. A successful create means the cart was created, not that the customer completed payment.

The bundled `/monoparts/callback` validates the order-state schema. Use it for QR only if the bank confirms that the callback has that same contract. For a different QR callback schema, register an application route, verify its original body signature using `SignerInterface`, validate the agreed fields, persist it in an inbox and acknowledge only after persistence. The package does not provide a generic QR callback parser.

When abandoning the basket, cancel by the store QR ID:

```php
$mono->cancelQrCart($qrId);
```

Cancellation returns `void` after a successful HTTP response and accepts `qr_id`, not `$bankCartId`. Serialize local operations on one QR so an old cancellation cannot cancel a newer basket using the same store QR. The package has no QR-cart lookup method and does not normalize create-QR HTTP 409 as an order duplicate. After an uncertain QR write, use the bank's QR recovery procedure instead of blindly retrying or inventing a status lookup.

## 10. Check client and broker eligibility

Client lookup can run before order creation to adjust the checkout experience:

```php
use Inkvizitoria\MonoParts\Http\MonoPartsClient;

$mono = app(MonoPartsClient::class);
$client = $mono->validateClientV2('+380500000001');
$isBankClient = $client->found;
```

`found` means the lookup found a client. It does not approve the purchase, reserve credit or guarantee that `createOrder()` will succeed. A negative lookup is a business result, not an HTTP exception. The method accepts an omitted phone because the bank schema permits it; for a customer-specific check, supply the actual validated phone.

Broker availability is a different endpoint. Use it only if your broker agreement provides a broker ID and employee/outlet identifiers. Configure the broker ID on the server:

```dotenv
MONOPARTS_BROKER_ID=your-broker-id
```

Then check the priced basket:

```php
$availability = $mono->brokerAvailability(
    amount: '1234.56',
    employeeId: 'employee-1',
    inn: '1234567890',
    outletId: 'outlet-1',
    phone: '+380500000001',
);

$installmentsAreAvailable = $availability->available;
```

The call uses `broker-id` instead of `store-id`. A final optional `brokerId` argument overrides the configured broker ID; select overrides from trusted server-side configuration. `available=false` is a normal eligibility result. `true` still does not replace creation, customer approval or store confirmation. Keep tax identifiers and phones out of request URLs and routine logs.

## 11. Handle errors and uncertain operations

Classify failures by whether the bank may have acted. Retain the merchant operation ID, request snapshot and local operation record before making a write.

| Result | What it establishes | Application response |
| --- | --- | --- |
| `ConfigurationException` or local `PayloadValidationException` | The client could not send a valid request | Fix settings or input; show field errors where appropriate |
| `ApiResponseException` | The bank returned a non-success HTTP response | Inspect `statusCode` and the bank message; distinguish an explicit rejection from a server failure |
| `TransportException` | Network failed or the response could not be interpreted | For writes, retain an uncertain operation and reconcile before replay |
| `SignatureValidationException` | A required response signature could not be trusted | Do not accept the result; a write may still have happened |
| Known negative DTO result | The API succeeded but the business answer was negative | Handle the declined/negative result without treating it as a network failure |
| Application exception after transport | A listener or local persistence failed | Reconcile: the remote operation may already be complete |

The HTTP client does not automatically retry. A job's retry mechanism is an application policy, not a guarantee that every bank operation can be repeated. State reads can use bounded retry/backoff; writes require operation-specific reconciliation. HTTP 500 after a write and a malformed successful response are both potentially ambiguous.

For example, catch field-validation errors at your checkout boundary:

```php
use Inkvizitoria\MonoParts\Exceptions\PayloadValidationException;

try {
    $payment = app(\App\Services\SubmitInstallmentPayment::class)->submit($payment->id);
} catch (PayloadValidationException $e) {
    return response()->json(['errors' => $e->errors()], 422);
}
```

Other failures should produce your application's pending/review or operational-error response; do not expose raw bank messages, stack traces or customer details to a public endpoint. Return a local payment reference so the customer can resume the same checkout.

The default log channel is `monoparts`, writing endpoint, HTTP status and normalized result to `storage/logs/monoparts.log`. Observe `ResponseReceived` when you need status metadata, and `CallbackFailed` for rejected callbacks. Keep observability listeners reliable: a thrown listener can turn a successful bank call into an application exception. The package has no general failed-outbound-request event; observe those failures at your service boundary.

For application logs, record your local payment/refund ID, endpoint, attempt count and exception type. Customer phones, tax IDs, request snapshots, documents and signatures require controlled storage and retention. The inbox fingerprint supports duplicate processing; it is not a bank transaction ID or a substitute for signature verification.

## 12. Test and deploy the integration

### Verify the application workflow offline

Use Laravel `Http::fake()` and `Http::preventStrayRequests()` to test your own persistence and state transitions. Keep queue processing under test control: use `Queue::fake()` to assert that inbox capture dispatches a job, then execute the job separately with fake bank responses. Do not use `Event::fake()` when testing the real `CallbackValidated` listener, since it prevents that listener from running.

A callback test should send the exact signed bytes:

```php
use Inkvizitoria\MonoParts\Security\Signer;

$body = json_encode([
    'order_id' => $payment->bank_order_id,
    'state' => 'SUCCESS',
    'order_sub_state' => 'SUCCESS',
], JSON_THROW_ON_ERROR);
$signature = (new Signer('test-secret'))->sign($body);

$response = $this->call('POST', '/monoparts/callback', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_SIGNATURE' => $signature,
], $body);

$response->assertOk();
```

Place this snippet in a Laravel feature test after configuring `monoparts.merchant.signature_secret` to `test-secret`, inserting a local payment with the bank UUID, and faking queue dispatch. Check the inbox row and job dispatch as well as the HTTP status.

Cover the whole lifecycle: repeated checkout, duplicate create response, create timeout recovery, callback before the bank ID is saved, duplicate/out-of-order callbacks, invalid signature, failed dispatch, pending and terminal states, confirmation timeout after issuance, competing refund reservations, uncertain refunds and repeated report imports. Verify that negative or unknown states never trigger fulfillment automatically.

The package repository has its own offline suite:

```bash
composer install
composer validate --strict
composer test
composer audit
```

These are package-development commands; the installed library's distribution excludes its tests. Your Laravel application's tests must also exercise its models, locks, jobs and operational recovery.

### Exercise the bank sandbox deliberately

For the package's optional integration suite, export credentials in your shell:

```bash
export MONOPARTS_RUN_INTEGRATION=1
export MONOPARTS_TEST_STORE_ID='your-sandbox-store-id'
export MONOPARTS_TEST_SIGNATURE_SECRET='your-sandbox-signing-secret'
export MONOPARTS_TEST_PHONE='your-sandbox-test-phone'
composer test:integration
```

The suite uses the fixed sandbox host. It skips without explicit opt-in and credentials. To also create a sandbox order and query its state, set `MONOPARTS_TEST_CREATE_ORDER=1`; that order remains in the sandbox. These tests do not load a local `.env` file automatically or prove that your public callback endpoint, worker or accounting integration is working.

For application acceptance testing, exercise the merchant flow enabled on your bank account and check the externally reachable callback URL, stored bank mapping, inbox, queue worker and fulfillment decision. Use the bank's documented sandbox identities and scenarios.

### Deploy the configured application

Switch to production credentials only after the sandbox workflow passes:

```dotenv
MONOPARTS_ENV=production
MONOPARTS_STORE_ID=your-production-store-id
MONOPARTS_SIGNATURE_SECRET=your-production-signing-secret
```

Rebuild application caches and restart long-running workers:

```bash
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan queue:restart
```

Check the public callback address generated by `route('monoparts.callback')`, including proxy/HTTPS configuration. If you change the route or hostname, consider outstanding attempts: their saved payloads contain the previous callback address. Keep that endpoint reachable while they complete or reconcile the change with the bank.

Enable response-signature enforcement with `MONOPARTS_VERIFY_RESPONSE_SIGNATURE=true` when your bank integration supplies signed HTTP responses. This setting makes missing response signatures fail; it does not disable or enable callback verification, which is always required. Check your merchant integration before changing it in production.

Monitor pending attempts, old inbox records, failed jobs, unresolved confirmations/refunds, unmatched report rows and callback 4xx/5xx responses. Configure recovery jobs and escalation ownership before accepting real purchases. The package's default timeout, signature and logging behavior are listed in the [reference](docs/reference.md).

## Sources, license and maintenance

Bank operations follow the [monobank API documentation](https://u2-demo-ext.mono.st4g3.com/docs/index.html) and [Swagger schema](https://u2-demo-ext.mono.st4g3.com/v2/api-docs). The application persistence, inbox, locking and reconciliation recipes are integration patterns supplied by this cookbook; they are not bank promises about delivery order, retry intervals or exactly-once execution.

MIT. See [LICENSE](LICENSE). Maintained by [Denis Drozh](https://github.com/Inkvizitoria). Report reproducible package issues at [GitHub Issues](https://github.com/Inkvizitoria/laravel-monoparts/issues).
