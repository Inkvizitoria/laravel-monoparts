# Upgrading from v1 to v2

## Runtime requirements

Version 2 supports Laravel 12 on PHP 8.2+ and Laravel 13 on PHP 8.3+. Upgrade applications running Laravel 8–11 before installing v2.

```bash
composer require inkvizitoria/laravel-monoparts:^2.0
php artisan vendor:publish --tag=monoparts-config
```

Publishing does not overwrite an existing configuration unless `--force` is supplied. Merge the new `http.timeout` and `http.connect_timeout` settings into your existing config; their defaults are 30 and 10 seconds. Rebuild Laravel's config cache after changing settings.

## Money fields

Request amounts keep their v1 meaning: numeric values represent UAH. Integer `100` means UAH 100.00, not 100 kopiykas. Use `Money::fromCents()` for minor units.

`returnOrder()` and `brokerAvailability()` now accept `Money|int|float|string`. Decimal strings are preferred. Extra fractional precision is rejected instead of rounded. `Money::fromDecimal()` requires exactly two fractional digits; `Money::fromNumber()` accepts zero, one or two.

The following response properties now return `Money|null` instead of `float|null`:

- `OrderShortInfo::totalSum`
- `ReverseEntry::sum`
- `DailyReportOrder::commission`, `creditSum`, `sentSum`, `totalSum`, `transferredSum`

Replace float arithmetic and comparisons with `toCents()`, `toDecimal()`, `equals()`, `add()` or `subtract()`. `commissionPercent` remains a float. Guarantee-letter data is returned as nested arrays and is not converted to `Money`.

## Payload and response validation

Nested create-order and QR objects reject unsupported fields. Product/program collections must be JSON lists; integer counts are normalized to JSON integers. Optional VAT and initial-payment values permit zero. Amounts are sent as JSON numbers with two decimal places.

Successful responses must contain valid JSON objects with the expected result fields. Missing result fields, malformed document/report structures, malformed JSON and wrong boolean types throw `TransportException`; they no longer return empty IDs or misleading boolean flags. Unknown high-level order states produce `ORDER_UNKNOWN` while retaining their raw value. Non-JSON HTTP errors throw `ApiResponseException` with the HTTP status preserved.

Base URLs must use HTTPS. Invalid environments, timeouts, credentials and HMAC algorithms fail before transport.

## Signing and callbacks

The default signer is `Security\Signer`. `Support\HmacSigner` remains available as a compatible wrapper. `InvalidSignatureException` extends `SignatureValidationException`; existing catch blocks for the parent exception remain valid.

A custom signer must implement `assertValid()` as well as `sign()` and `verify()`. Bind `SignerInterface` before the client is resolved. Unknown driver settings fail instead of recursively resolving the package's own signer binding.

`CallbackReceived` is emitted once per callback. Unverified bodies are not decoded or exposed through this event. Use `CallbackValidated` to apply order updates. Exceptions from a synchronous validated listener return HTTP 500; the package does not deduplicate callbacks.

The unused `callbacks.event` config key has been removed. Register Laravel listeners for the concrete package events.

## New methods

- `guaranteeLetterData()` and `guaranteeLetterDataV2()` return document data.
- `guaranteeLetter()` returns PDF bytes.
- `createQrCart()` returns the cart ID; `cancelQrCart()` returns void.

## Development and packaging

PSR-4 namespaces and Laravel auto-discovery metadata are corrected. Versioning comes from Git tags; `composer.json` no longer contains a fixed version.

`composer test` runs offline tests. `composer test:integration` is a separate, opt-in sandbox suite requiring environment-provided credentials. It no longer runs by default or embeds test credentials.

IDE files, downloaded API documents, generated Testbench caches, logs and the local Composer lock file are excluded from the repository. Distribution archives also exclude tests and CI files.
