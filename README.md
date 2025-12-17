# Laravel MonoParts

Пакет для інтеграції Monobank «Покупка частинами» у Laravel 8–12 (PHP 8.1+). Дає строгі правила валідації, підпис/верифікацію запитів і колбеків, typed DTO-відповіді, доменні статуси, події та конфігурований логер. Не вимагає БД.

## Зміст
- [Фічі](#фічі)
- [Вимоги](#вимоги)
- [Встановлення](#встановлення)
- [Конфігурація](#конфігурація)
- [Підпис запитів і відповідей](#підпис-запитів-і-відповідей)
- [Швидкий старт](#швидкий-старт)
- [Методи API](#методи-api)
- [DTO та статуси](#dto-та-статуси)
- [Колбеки](#колбеки)
- [Події](#події)
- [Винятки](#винятки)
- [Логи](#логи)
- [Розширення](#розширення)
- [Тестування](#тестування)
- [Автор](#автор)

## Фічі
- Повна типізація та строгі payload-правила, що відповідають документації Monobank.
- Підписування кожного запиту та перевірка колбеків через HMAC SHA256 + Base64.
- Відповіді нормалізовані в DTO з доменними статусами (enum).
- Події до/після HTTP-запиту та для колбеків.
- Окремий канал логування з можливістю перевизначення.
- Мінімум залежностей, пакет не тягне Laravel як обов’язкову залежність, крім `illuminate/*`.

## Вимоги
- PHP 8.1+
- Laravel 8–12
- ext-json

## Встановлення
```bash
composer require inkvizitoria/laravel-monoparts
php artisan vendor:publish --provider="Inkvizitoria\\MonoParts\\Providers\\MonoPartsServiceProvider" --tag=config
```

## Конфігурація
`config/monoparts.php`:

- `environment`: `sandbox|stage|production`
- `production_url`: базовий URL прод середовища
- `merchant.store_id`, `merchant.signature_secret`, `merchant.broker_id`
- `signature.header` (за замовчуванням `signature`), `signature.algo`
- `headers.store`, `headers.broker` для кастомних назв хедерів
- `callbacks.*` для ввімкнення/шляху/мідляр
- `logging.*` канал або локальний файл

`.env` приклад:
```
MONOPARTS_ENV=production
MONOPARTS_STORE_ID=your-store-id
MONOPARTS_SIGNATURE_SECRET=your-hmac-secret
MONOPARTS_BROKER_ID=optional-broker-id
```

Середовища та базові URL:
- sandbox: `https://u2-demo-ext.mono.st4g3.com`
- stage: `https://u2-ext.mono.st4g3.com`
- production: `https://u2.monobank.com.ua`

## Підпис запитів і відповідей
Підпис передається в заголовку `signature`. Пакет підписує **JSON body** кожного запиту:

```
signature = base64_encode(
    hash_hmac('sha256', json_body, signature_secret, true)
)
```

JSON формується через `json_encode` з UTF-8. Для колбеків модуль перевіряє підпис тим самим алгоритмом.

## Швидкий старт
```php
use Inkvizitoria\MonoParts\Facades\MonoParts;

$order = MonoParts::createOrder([
    'store_order_id' => 'ORDER-001',
    'client_phone' => '+380501234567',
    'total_sum' => 1234.56,
    'invoice' => [
        'date' => '2024-05-01',
        'number' => 'INV-1',
        'source' => 'INTERNET',
    ],
    'available_programs' => [
        ['available_parts_count' => [3, 6, 10], 'type' => 'payment_installments'],
    ],
    'products' => [
        ['name' => 'Телевізор', 'count' => 1, 'sum' => 1234.56],
    ],
    'result_callback' => 'https://example.com/monoparts/result',
]);

$paid = MonoParts::checkPaid($order->orderId);
$state = MonoParts::orderState($order->orderId);
```

## Методи API
Усі методи доступні через фасад `MonoParts` або DI через `MonoPartsClient`.

### Check Paid
- Сигнатура: `MonoParts::checkPaid(string $orderId): CheckPaidResult`
- Endpoint: `/api/order/check/paid`
- Payload: `order_id` (UUID-рядок)
- Returns: `CheckPaidResult { fullyPaid: bool, bankCanReturnMoneyToCard: bool }`
- ResponseStatus: `CHECK_PAID_YES` або `CHECK_PAID_NO`
- Throws: `PayloadValidationException`, `ApiResponseException`, `TransportException`, `ConfigurationException`
- Приклад:
```php
$result = MonoParts::checkPaid($orderId);
if ($result->fullyPaid) {
    // success
}
```

### Create Order
- Сигнатура: `MonoParts::createOrder(array $payload): CreateOrderResult`
- Endpoint: `/api/order/create`
- Headers: `signature`, `store-id`
- Returns: `CreateOrderResult { orderId: string }`
- ResponseStatus: `ORDER_CREATED` або `ORDER_DUPLICATE` (HTTP 409 не кидає виняток)
- Throws: `PayloadValidationException`, `ApiResponseException`, `TransportException`, `ConfigurationException`

Payload приклад:
```php
[
    'store_order_id' => 'ORD-1',
    'client_phone' => '+380501234567',
    'total_sum' => 100.25,
    'invoice' => [
        'date' => '2024-01-01',
        'number' => '1',
        'source' => 'INTERNET',
        'point_id' => 'P-1',
    ],
    'available_programs' => [
        ['available_parts_count' => [3, 6], 'type' => 'payment_installments'],
    ],
    'products' => [
        ['name' => 'Товар', 'count' => 1, 'sum' => 100.25],
    ],
    'result_callback' => 'https://example.com/callback',
    'financial_company_merchant_info' => [
        'edrpou_code' => '12345678',
        'iban_account' => 'UA123456789012345678901234567',
        'store_name' => 'Shop',
    ],
    'additional_params' => [
        'nds' => 1.0,
        'seller_phone' => '+380501234567',
        'ext_initial_sum' => 10.0,
    ],
]
```

Валідація:
```
store_order_id: string, 1..64
client_phone: +380XXXXXXXXX
total_sum: decimal(2), min 1
invoice.date: Y-m-d
invoice.number: string
invoice.point_id: optional, 1..50
invoice.source: STORE|INTERNET|CHECKOUT
available_programs[].available_parts_count[]: int >= 1
available_programs[].type: payment_installments
products[].name: string, 1..500
products[].count: int >= 1
products[].sum: decimal(2), min 0.01
result_callback: url (optional)
financial_company_merchant_info.edrpou_code: digits (optional)
financial_company_merchant_info.iban_account: UA + 27 digits (optional)
financial_company_merchant_info.store_name: string (optional)
additional_params.nds: numeric (optional)
additional_params.seller_phone: +380XXXXXXXXX (optional)
additional_params.ext_initial_sum: numeric (optional)
```
- Приклад:
```php
$result = MonoParts::createOrder($payload);
$orderId = $result->orderId;
```

### Confirm Order
- Сигнатура: `MonoParts::confirmOrder(string $orderId): OrderStateInfo`
- Endpoint: `/api/order/confirm`
- Payload: `order_id` (UUID-рядок)
- Returns: `OrderStateInfo`
- ResponseStatus: `ORDER_SUCCESS`, `ORDER_FAIL`, `ORDER_IN_PROCESS`
- Throws: `PayloadValidationException`, `ApiResponseException`, `TransportException`, `ConfigurationException`
- Приклад:
```php
$info = MonoParts::confirmOrder($orderId);
```

### Reject Order
- Сигнатура: `MonoParts::rejectOrder(string $orderId): OrderStateInfo`
- Endpoint: `/api/order/reject`
- Payload: `order_id` (UUID-рядок)
- Returns: `OrderStateInfo`
- ResponseStatus: `ORDER_SUCCESS`, `ORDER_FAIL`, `ORDER_IN_PROCESS`
- Throws: `PayloadValidationException`, `ApiResponseException`, `TransportException`, `ConfigurationException`
- Приклад:
```php
$info = MonoParts::rejectOrder($orderId);
```

### Order State
- Сигнатура: `MonoParts::orderState(string $orderId): OrderStateInfo`
- Endpoint: `/api/order/state`
- Payload: `order_id` (UUID-рядок)
- Returns: `OrderStateInfo`
- ResponseStatus: `ORDER_SUCCESS`, `ORDER_FAIL`, `ORDER_IN_PROCESS`
- Throws: `PayloadValidationException`, `ApiResponseException`, `TransportException`, `ConfigurationException`
- Приклад:
```php
$info = MonoParts::orderState($orderId);
```

### Order Data
- Сигнатура: `MonoParts::orderData(string $orderId): OrderShortInfo`
- Endpoint: `/api/order/data`
- Payload: `order_id` (UUID-рядок)
- Returns: `OrderShortInfo`
- Throws: `PayloadValidationException`, `ApiResponseException`, `TransportException`, `ConfigurationException`
- Поля `OrderShortInfo`:
```
createTimestamp: DateTimeImmutable|null
iban: string|null
invoiceDate: string|null
invoiceNumber: string|null
maskedCard: string|null
pointId: string|null
reverseList: ReverseEntry[]
source: string|null
storeOrderId: string|null
totalSum: float|null
```
- Поля `ReverseEntry`:
```
sum: float|null
timestamp: DateTimeImmutable|null
```
- Приклад:
```php
$info = MonoParts::orderData($orderId);
$storeOrderId = $info->storeOrderId;
```

### Return Order
- Сигнатура: `MonoParts::returnOrder(string $orderId, float $sum, bool $returnMoneyToCard, string $storeReturnId, array $additionalParams = []): ReturnResponse`
- Endpoint: `/api/order/return`
- Payload: `order_id`, `sum`, `return_money_to_card`, `store_return_id`, `additional_params`
- Returns: `ReturnResponse { status: ReturnStatus, rawStatus: string|null }`
- ResponseStatus: `RETURN_OK` або `RETURN_ERROR`
- Throws: `PayloadValidationException`, `ApiResponseException`, `TransportException`, `ConfigurationException`
- Приклад:
```php
$result = MonoParts::returnOrder($orderId, 10.0, true, 'RET-1', ['nds' => 1.5]);
```

### Store Report
- Сигнатура: `MonoParts::storeReport(string $date): DailyReport`
- Endpoint: `/api/store/report`
- Payload: `date` у форматі `Y-m-d`
- Returns: `DailyReport` (масив `DailyReportOrder`)
- Throws: `PayloadValidationException`, `ApiResponseException`, `TransportException`, `ConfigurationException`
- Поля `DailyReportOrder`:
```
cardNumber, commission, commissionPercent, createDateTime, creditSum,
invoiceNumber, odbContractNumber, operationTimestamp, orderDate, orderId,
payParts, paymentDate, sentSum, terminalId, totalSum, transactionDate,
transactionId, transferredSum
```
- Приклад:
```php
$report = MonoParts::storeReport('2024-01-01');
$count = count($report->orders);
```

### Validate Client V2
- Сигнатура: `MonoParts::validateClientV2(?string $phone = null): ValidateClientResponse`
- Endpoint: `/api/v2/client/validate`
- Payload: `phone` (optional, формат `+380XXXXXXXXX`)
- Returns: `ValidateClientResponse { found: bool }`
- ResponseStatus: `CLIENT_FOUND` або `CLIENT_NOT_FOUND`
- Throws: `PayloadValidationException`, `ApiResponseException`, `TransportException`, `ConfigurationException`
- Приклад:
```php
$validation = MonoParts::validateClientV2('+380501234567');
```

### Broker Availability
- Сигнатура: `MonoParts::brokerAvailability(float $amount, string $employeeId, string $inn, string $outletId, string $phone, ?string $brokerId = null): InstallmentAvailabilityResponse`
- Endpoint: `/api/fin/broker/check/installment/availability`
- Headers: `signature`, `broker-id` (store-id не потрібен)
- Returns: `InstallmentAvailabilityResponse { available: bool }`
- ResponseStatus: `AVAILABLE` або `NOT_AVAILABLE`
- Throws: `PayloadValidationException`, `ApiResponseException`, `TransportException`, `ConfigurationException`
- Приклад:
```php
$availability = MonoParts::brokerAvailability(1000.0, 'emp1', '1234567890', 'outlet1', '+380501234567');
```

## DTO та статуси
`MonoPartsResponse` доступний через подію `ResponseReceived`:
- `status`: `ResponseStatus`
- `httpStatus`: int
- `raw`: оригінальний JSON масив або `null`
- `data`: DTO
- `headers`: масив заголовків

Приклад доступу до статусу:
```php
use Inkvizitoria\MonoParts\Events\ResponseReceived;

Event::listen(ResponseReceived::class, function (ResponseReceived $event) {
    $status = $event->response->status->value;
    $raw = $event->response->raw;
});
```

`ResponseStatus`:
```
order_success
order_fail
order_in_process
order_created
order_duplicate
return_ok
return_error
check_paid_yes
check_paid_no
available
not_available
client_found
client_not_found
success_http
client_error_http
server_error_http
```

`OrderState`:
```
SUCCESS
FAIL
IN_PROCESS
```

`OrderSubState`:
```
ADDED
INTERNAL_INIT
INTERNAL_INIT_PRE_ACTIVATE
INTERNAL_INIT_DEBIT
TESTING
INTERNAL_ADDED
INTERNAL_CHECKED
INTERNAL_WAITING_FOR_IBUS_PDFBOX
CLIENT_NOT_FOUND
WRONG_CLIENT_APP_VERSION
EXCEEDED_SUM_LIMIT
ACCOUNT_CLOSED
PAY_PARTS_ARE_NOT_ACCEPTABLE
CLIENT_CONFIRM_TIME_EXPIRED
WAITING_FOR_CLIENT
REJECTED_BY_CLIENT
REJECTED_BY_STORE
WAITING_FOR_STORE_CONFIRM
SUCCESS
```

`ReturnStatus`:
```
OK
ERROR
```

## Колбеки
За замовчуванням маршрут `POST /monoparts/callback` (налаштовується в `monoparts.callbacks`).

Правила валідації:
```
order_id: UUID
state: SUCCESS|FAIL|IN_PROCESS
order_sub_state: [A-Z_]+ (optional)
message: string (optional)
```

Відповідь колбеку:
- 200: `{"message":"ok"}`
- 400: `{"message":"Payload validation failed.","errors":{...}}`
- 403: `{"message":"Invalid callback signature."}`
- 500: `{"message":"error"}`

## Події
- `RequestSending($endpoint, $payload)`
- `ResponseReceived(MonoPartsResponse $response)`
- `CallbackReceived($payload, ?string $signature)`
- `CallbackValidated($payload, string $signature, ?OrderStateInfo $stateInfo)`
- `CallbackFailed($payload, ?string $signature, Throwable $exception)`

## Винятки
- `MonoPartsException` — базовий клас для всіх помилок пакета (успадковує `RuntimeException`).
- `ConfigurationException` — відсутній `store_id` або `broker_id`; кидається перед відправкою запиту.
- `PayloadValidationException` — невірний payload; містить `errors(): array` з детальними помилками валідації.
- `ApiResponseException` — Monobank повернув 4xx/5xx (крім 409 для дубля); містить `statusCode: int` і `exceptionResponse: ExceptionResponse` з полем `message`.
- `TransportException` — транспортна помилка HTTP або непередбачений збій; містить `getPrevious()` з первинним ексепшеном.
- `SignatureValidationException` — невірний підпис колбеку; використовується в CallbackProcessor.

Приклад обробки:
```php
use Inkvizitoria\MonoParts\Exceptions\ApiResponseException;
use Inkvizitoria\MonoParts\Exceptions\MonoPartsException;
use Inkvizitoria\MonoParts\Exceptions\PayloadValidationException;
use Inkvizitoria\MonoParts\Exceptions\TransportException;

try {
    $order = MonoParts::createOrder($payload);
} catch (PayloadValidationException $e) {
    $errors = $e->errors();
} catch (ApiResponseException $e) {
    $status = $e->statusCode;
    $message = $e->exceptionResponse->message;
} catch (TransportException $e) {
    $previous = $e->getPrevious();
} catch (MonoPartsException $e) {
    // fallback for any other package exception
}
```

## Логи
Логуються лише службові повідомлення (без payload). Канал задається в `monoparts.logging` і може бути перевизначений через `.env`. Якщо канал відсутній у `logging.channels`, пакет автоматично реєструє його на базі `monoparts.logging.channel_config`.

## Розширення
- Власний підписувач: забіндьте `SignerInterface` у контейнері або використайте `signature.driver=custom`.
- Власний обробник колбеку: забіндьте `CallbackHandlerInterface`.
- Кастомні хедери: `headers.store`, `headers.broker`, `signature.header`.

## Тестування
```bash
composer test
```

Інтеграційні тести ходять у реальний sandbox Monobank:
```
base: https://u2-demo-ext.mono.st4g3.com
store-id: test_store_with_confirm
signature secret: secret_98765432--123-123
```

Запустити лише інтеграційні:
```bash
vendor/bin/phpunit --group integration
```

Запустити без інтеграційних:
```bash
vendor/bin/phpunit --exclude-group integration
```

## Автор
Drozh Denis (DD) — `inkvizitoria/laravel-monoparts`.
