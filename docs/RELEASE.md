# FailureLab release verification

## Latest stable release — v1.2.0 (2026-10-16)

- [GitHub stable release](https://github.com/mykholy/laravel-failurelab/releases/tag/v1.2.0), immutable source SHA `9653af99d3888d956748df2da066e22e53d4ff38`.
- [Public release validation](https://github.com/mykholy/laravel-failurelab/actions/runs/38939862511) succeeded, including exact-source Packagist indexing and real Composer registry installs **without VCS/path overrides** on Laravel 12 and 13.
- [Core CI](https://github.com/mykholy/laravel-failurelab/actions/runs/38939735373) passed PHP 8.3–8.5, Laravel 12/13, MySQL 8.4, PostgreSQL 17, PHPStan, Pint, dependency audit and independent source export.
- [Linux queue-crash drill](https://github.com/mykholy/laravel-failurelab/actions/runs/38939735335) passed a genuine SIGKILL followed by fresh-worker recovery on clean Laravel 12 and 13; this remains a CI fixture, **not** a new general-purpose public worker-control API.
- The **additive public API** is `Testing\FakeCaptureGateway` plus its sanitized `SimulatedUnknownOutcome`, with idempotency and reconciliation negative controls.
- Earlier `Scenario` / `Replay` methods are backward compatible and no actual external payment requests occur.

## Previous stable release — v1.1.0 (2026-10-16)

- **GitHub Release:** [v1.1.0](https://github.com/mykholy/laravel-failurelab/releases/tag/v1.1.0) (stable, not a prerelease).
- **Packagist:** [mykholy/laravel-failurelab](https://packagist.org/packages/mykholy/laravel-failurelab), where v1.1.0 resolves to exactly `dd39fcdea2fe4404f41dc216f1eb39b7247ad7c8`, matching the GitHub tag.
- **Public installation:** fresh Laravel 12 and 13 applications installed v1.1.0 **without path/VCS repository overrides**, exercised auto-discovery, bounded replay and acknowledgement-loss assertions.
- **Verified main CI:** [standalone 14/14 jobs](https://github.com/mykholy/laravel-failurelab/actions/runs/38894477508), [VCS 2/2](https://github.com/mykholy/laravel-failurelab/actions/runs/38894477461), [Packagist consumers 2/2](https://github.com/mykholy/laravel-failurelab/actions/runs/38894477448), [versioned consumers 2/2](https://github.com/mykholy/laravel-failurelab/actions/runs/38894477386), [pinned v1.1.0 2/2](https://github.com/mykholy/laravel-failurelab/actions/runs/38894477359).
- **Scope:** deterministic in-process test faults and bounded sequential redelivery only. No real process SIGKILL, network packet loss, concurrent worker schedule, independent production security certification or externally guaranteed exactly-once behavior.

The documentation/CI changes merged *after* the v1.1.0 tag are maintained on `main`; they do not silently change the published artifact.

## Future stable release process

The maintainer initiates **Publish verified stable release** through GitHub Actions on `main`, supplying a **new** version such as `v1.1.1`, after updating `CHANGELOG.md`. The workflow must refuse to publish if the requested tag or release already exists, if `main` moved since the dispatch SHA, or if exact-source CI/consumer gates are missing or unsuccessful. It creates immutable GitHub release assets only for a reviewed source, then verifies Packagist indexing against the same SHA and installs the public release in Laravel 12/13. If Packagist or public installation fails, **investigate without retagging or moving the existing tag**.

The package release is not a security certification. See [SAFETY.md](SAFETY.md); update this document and the changelog for each new release, and record any remaining adoption or host-specific checks.

## Historical v1.0.0 evidence

**GitHub status: v1.0.0 stable release published and tested.**
**Packagist status: registered and v1.0.0 installed in fresh Laravel 12/13 consumers with no Composer repository overrides.**
The stable tag is immutable: `252e7256bb488b1cefffe6d6b9842759c9227e22`.

## Passed release gates

- [x] Source migrated into the standalone repository with all canonical files
- [x] Dependency-free smoke tests on PHP 8.3, 8.4 and 8.5
- [x] PHPUnit/Testbench Laravel 12/13 compatibility matrix
- [x] PHPStan level 5, Laravel Pint and Composer dependency audit
- [x] MySQL 8.4 and PostgreSQL 17 real transaction/replay tests
- [x] Clean Laravel 12/13 path-based Composer installation
- [x] Clean Laravel 12/13 remote GitHub Composer VCS installation from `dev-main`
- [x] Published and verified `v0.1.0-beta.1` pre-release Git tag
- [x] Clean Laravel 12/13 independent Composer VCS install of `v0.1.0-beta.1`
- [x] Stable `v1.0.0` tag and GitHub Release created at an exact tested commit
- [x] Clean Laravel 12 and 13 Composer VCS install of the `v1.0.0` tag, including Laravel provider auto-discovery and recovery assertions
- [x] Negative controls for swallowed/forged failures and non-idempotent provider
- [x] Limits and threat model explicitly documented

Evidence: [standalone CI](https://github.com/mykholy/laravel-failurelab/actions/runs/38891346888),
[pre-release tag consumers](https://github.com/mykholy/laravel-failurelab/actions/runs/38891170992),
[stable GitHub publish + pinned stable installs](https://github.com/mykholy/laravel-failurelab/actions/runs/38891495380),
[Packagist registry-only consumers](https://github.com/mykholy/laravel-failurelab/actions/runs/38892927867).

## Remaining adoption and maintenance work

- [x] Packagist registration completed; v1.0.0 resolves via Composer registry without VCS/path overrides
- [x] In clean Laravel 12 and 13 apps installed `composer require --dev mykholy/laravel-failurelab:^1.0` and verified runtime auto-discovery
- [x] Confirm Packagist indexed the **new v1.1.0 tag**, with clean Laravel 12/13 registry-only installation
- [ ] Collect public developer adoption feedback and follow up on edge cases
- [ ] Maintain versioned release notes for later patches without moving `v1.0.0`

## Scope limitations

This is a test-only **in-process exception/fault and sequential replay** harness. It does not kill workers, simulate real infrastructure failure, or guarantee exactly-once network/financial effects. Use fake providers and disposable DBs, even when `APP_ENV=testing`.


## 1.1.0 release evidence

- [x] Additive `loseAcknowledgementOn(int)` API with immutable builders and fail-closed assertions.
- [x] Testbench/PHPUnit plus real SQLite/MySQL/PostgreSQL recovery fixture.
- [x] Existing Laravel 12/13 / PHP 8.3–8.5 quality matrix and Composer audit passed.
- [x] Published GitHub `v1.1.0` from exact source commit `dd39fcdea2fe4404f41dc216f1eb39b7247ad7c8`.
- [x] Composer Git VCS installs of `v1.1.0` in clean Laravel 12 and 13 applications executed the new recovery assertions.

Release: https://github.com/mykholy/laravel-failurelab/releases/tag/v1.1.0

Evidence: https://github.com/mykholy/laravel-failurelab/actions/runs/38893786691

The GitHub release and its tag are stable. **Packagist v1.1.0 indexing was independently verified** in clean Laravel 12 and 13 consumers with no Composer repository overrides: https://github.com/mykholy/laravel-failurelab/actions/runs/38894111452 No worker SIGKILL or real transport assertions are implied.
