# FailureLab documentation

**Break the test. Prove the recovery.** Start with one well-defined application
invariant: a committed payment, a webhook event, or a durable local effect.

## Get started

| Goal | Best starting point | What you will verify |
| --- | --- | --- |
| First successful test (5 minutes) | [Quick start](QUICKSTART.md) | Exact interruption, bounded retry, final completion and invariant |
| Scenario already runs, but tests go green incorrectly | [Recovery patterns](RECOVERY_PATTERNS.md) | Required fault-plan and negative controls |
| Checkout provider returns an unknown outcome | [Fake capture provider](FAKE_PROVIDER.md) | Stable idempotency key and read-only reconciliation |
| A webhook handler commits but ack is lost | [Recovery patterns](RECOVERY_PATTERNS.md) | Controlled sequential replay and later acknowledgement |
| A real queue worker dies after a DB commit | [Real Linux SIGKILL drill](REAL_WORKER_CRASH.md) | Disposable process isolation, lease expiry and recovery |
| Need an executable example today | [Runnable demos](../examples/README.md) | Explicitly scoped, repeatable tests |

## Understand the contract

- [Design and API decisions](DESIGN.md) — guarantees vs non-goals
- [Security / threat model](SAFETY.md) — testing-only and dependency isolation
- [Verified release evidence](RELEASE.md) — exact tags, PHP/Laravel, DB and Composer consumer checks
- [Changelog](../CHANGELOG.md) — what is actually published versus under development
- [Contributing](../CONTRIBUTING.md) — reproducible bug reports and regression tests

## Environment compatibility

The published v1.1.0 package targets **PHP 8.3+**, **Laravel 12/13**, and
Composer 2. The new provider fixture and process drill are development work
until included in a separately verified release. The process drill itself
requires **Linux** and a disposable SQLite-backed Laravel host.

## Important interpretation

FailureLab asserts test execution paths; **your application** must still prove
its database and provider invariants. Sequential callback replay does not
replicate network or process concurrency. The separate SIGKILL drill verifies a
specific Linux database-queue recovery fixture; it does not upgrade the
public Scenario runner into a general process manager.

[Back to repository](../README.md)
