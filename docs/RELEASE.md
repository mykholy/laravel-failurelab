# FailureLab release verification

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
- [ ] Verify Packagist automatic GitHub tag synchronization on the **next** versioned release
- [ ] Collect public developer adoption feedback and follow up on edge cases
- [ ] Maintain versioned release notes for later patches without moving `v1.0.0`

## Scope limitations

This is a test-only **in-process exception/fault and sequential replay** harness. It does not kill workers, simulate real infrastructure failure, or guarantee exactly-once network/financial effects. Use fake providers and disposable DBs, even when `APP_ENV=testing`.
