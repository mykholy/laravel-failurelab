# FailureLab standalone release readiness

**Status: standalone source candidate — stable tag and Packagist publication not yet verified.**

## Verified in the original factory

- [x] PHP 8.3–8.5 smoke tests; Laravel 12/13 PHPUnit + Testbench
- [x] Pint, PHPStan level 5, and clean Laravel 12/13 path-based consumer installs
- [x] MySQL 8.4 and PostgreSQL 17 transaction recovery, duplicate suppression and error-boundary tests
- [x] False-green regression tests: swallowed/forged fault exceptions, unsafe Unicode/control names, non-idempotent provider fake

Evidence: [full successful matrix](https://github.com/mykholy/mavvix-package-factory/actions/runs/38888341081), original factory commit `674d01e`.

## Outstanding release gates

- [ ] Complete standalone CI at the exact release candidate commit
- [ ] Dependency audit and manual test-only environment/security review
- [ ] Review all public API names and SemVer compatibility, with final changelog
- [ ] Independently verify a **tagged** candidate Git/VCS install into Laravel 12 and 13
- [ ] Get documented feedback from an external developer using a disposable integration
- [ ] Approve the precise release commit and known limitations
- [ ] Publish an immutable stable `v1.0.0` tag and GitHub Release
- [ ] Register/update Packagist and test `composer require --dev mykholy/laravel-failurelab:^1.0` without local path or VCS overrides

## Publication principles

FailureLab verifies explicitly instrumented **in-process fault injection** and **sequential replay**, not real worker SIGKILL, concurrent races, or exactly-once financial/transport outcomes. A successful workflow is not evidence of a published stable release. Never mark a release stable before completing the independent checks.
