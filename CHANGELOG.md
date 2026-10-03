# Changelog

## 2.0.0 — 2026-10-03

### Added

- Guarantee-letter data endpoints, PDF retrieval, QR cart creation and cancellation.
- Exact UAH `Money` values, monetary validation and deterministic JSON serialization.
- Configurable request and connection timeouts, plus HTTPS and environment validation.
- Order sub-states present in the current bank schema.
- Offline regression tests, explicit sandbox test opt-in and a Laravel/PHP CI matrix.
- English API documentation, migration instructions and an MIT license file.

### Fixed

- PSR-4 autoloading and Laravel provider/facade discovery.
- Incomplete monetary normalization in create-order payloads and public refund/broker methods.
- Integer response amounts interpreted as minor units, zero report values, float precision loss and money overflow.
- Invalid JSON numeric literals and empty request bodies serialized as arrays.
- Duplicate callback-received events and an invalid final exception inheritance hierarchy.
- Malformed API responses accepted as success and scalar error responses causing type errors.
- Recursive resolution of an unsupported signature driver.

### Changed

- Require PHP 8.2+ and Laravel 12/13; Laravel 13 requires PHP 8.3+.
- Return `Money|null` for monetary order/report fields. See [UPGRADING.md](UPGRADING.md).
- Reject unsupported nested payload fields, non-list collections and extra monetary precision.
- Resolve package versions from Git tags instead of a hard-coded Composer version.
- Exclude sandbox HTTP calls from the default test suite and local artifacts from releases.

## 1.0.0 — 2025-12-17

- Initial Laravel integration for order operations, callbacks, HMAC signing, reports and broker availability.
