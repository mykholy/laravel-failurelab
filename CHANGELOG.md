# Changelog

All notable changes will be documented here after the first public tag.

## [1.1.0] - 2026-10-16

GitHub release: https://github.com/mykholy/laravel-failurelab/releases/tag/v1.1.0

### Added

- Explicit, deterministic `Replay::loseAcknowledgementOn(int)` for an application callback that completed but whose simulated acknowledgement is lost.
- `ReplayReport::assertAcknowledgementPlanSatisfied()`, `assertAcknowledgementLostOn()`, and `assertAcknowledgedAfterLoss()` to reject skipped losses and false-positive recovery.
- Real SQLite/MySQL/PostgreSQL ledger-idempotency integration fixture and regression tests for handler failure, missing acknowledgement, bounds, duplicate scheduling, and immutable builders.
- Backward-compatible report constructor defaults; the existing `summary()` schema remains unchanged for v1.0 consumers.

## [1.0.0] - 2026-10-16

### Added

- Bounded deterministic named-checkpoint fault injection and sequential webhook replay.
- Explicit recovery and business invariant assertions with redacted assertion messages.
- Per-fault plan verification, opt-in redelivery following handler failure, and replay failure assertions.
- PHPUnit/Testbench compatibility checks for Laravel 12 and 13 on PHP 8.3–8.5.
- Consumer installation checks in fresh Laravel 12/13 applications.
- PHPStan level-5 gate and real MySQL 8.4 / PostgreSQL 17 transaction and replay fixtures.
- Negative controls for swallowed/forged injected exceptions and non-idempotent fake providers.

### Security

- Only an exception instance issued by the active test checkpoint can trigger automatic retry.
- A swallowed injected failure cannot satisfy the fault-plan assertion.
- Reject ASCII control bytes, invalid UTF-8 and bidirectional formatting controls in scenario names.

**Release:** [v1.0.0 on GitHub](https://github.com/mykholy/laravel-failurelab/releases/tag/v1.0.0). Stable tag installs were independently checked in Laravel 12 and 13 from Composer Git VCS. Packagist-only Composer installation was verified in clean Laravel 12 and 13 projects: [CI evidence](https://github.com/mykholy/laravel-failurelab/actions/runs/38892927867).
