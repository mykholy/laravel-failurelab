# Changelog

All notable changes will be documented here after the first public tag.

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

**Release:** [v1.0.0 on GitHub](https://github.com/mykholy/laravel-failurelab/releases/tag/v1.0.0). Stable tag installs were independently checked in Laravel 12 and 13 from Composer Git VCS. Packagist registration and registry-only installation have not yet been verified.
