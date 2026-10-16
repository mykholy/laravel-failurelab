<p align="center">
  <strong>FAILURELAB</strong><br>
  <em>Break the test. Prove the recovery.</em>
</p>

<h1 align="center">Laravel FailureLab</h1>
<p align="center">Deterministic fault injection and replay assertions for Laravel integration tests.</p>
<p align="center">
  <a href="https://github.com/mykholy/laravel-failurelab/actions/workflows/tests.yml"><img alt="Standalone CI" src="https://github.com/mykholy/laravel-failurelab/actions/workflows/tests.yml/badge.svg"></a>
  <img alt="PHP 8.3+" src="https://img.shields.io/badge/PHP-8.3%2B-777BB4">
  <img alt="Laravel 12 or 13" src="https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20">
  <a href="LICENSE"><img alt="MIT" src="https://img.shields.io/badge/license-MIT-green"></a>
</p>

> **Stable release: [v1.0.0](https://github.com/mykholy/laravel-failurelab/releases/tag/v1.0.0)** — GitHub tag published and independently installed in clean Laravel 12/13 applications. Packagist registration is separate and must be verified before claiming one-command registry installation.

A payment provider accepts a charge, but your application throws before saving the result. A webhook arrives three times. How do you prove your code won't create the local effect twice?

FailureLab runs the **same operation under controlled, named failure points**, records which attempts actually ran, and makes the assertions readable. It never intercepts HTTP or background workers by magic; you explicitly opt into each test seam.

```text
     your test                  app behavior                 the evidence
 ┌─────────────────┐        ┌───────────────────┐      ┌────────────────┐
 │ Scenario        │ -----> │ fake provider      │      │ Attempt #1     │
 │ fail once at    │        │ accepts capture    │ ---> │ interrupted    │
 │ provider.accepted│       │ checkpoint.hit()  │      ├────────────────┤
 │ retry once      │ -----> │ retry operation    │ ---> │ Attempt #2     │
 └─────────────────┘        │ persist locally   │      │ completed      │
                            └───────────────────┘      └────────────────┘
```

## Why another testing package?

Laravel's Queue/Bus fakes verify dispatch behavior. [Race Lab](https://packagist.org/packages/race-lab/laravel-race-lab) tests **concurrent workers at checkpoints**. FailureLab focuses on **bounded sequential replay of one business operation** after a controlled in-process interruption: a reproducible recovery assertion suitable for an isolated PHPUnit test.

It does **not** replace Race Lab, Horizon, Pulse, FlowGuard, Testbench, real subprocess crash tests or real distributed-systems integration tests. It makes no exactly-once delivery guarantee.

## Installation

**Verified on Packagist:** clean Laravel 12 and 13 applications installed `v1.0.0`
without any custom Composer VCS/path repository:

```bash
composer require --dev mykholy/laravel-failurelab:^1.0
```

The [Packagist-only consumer matrix](https://github.com/mykholy/laravel-failurelab/actions/runs/38892927867)
checks installation and auto-discovery independently. Version 1.1's
acknowledgement-loss API is currently in development and **not part of 1.0.0**.

**Requirements:** PHP 8.3+, Laravel 12 or 13, PHPUnit/Pest in the host app. Keep this in `require-dev`. Set `APP_ENV=testing` in `phpunit.xml` and use a disposable test database. No migrations, middleware, queues, services, or network access are installed by FailureLab.

## Example: a provider succeeded, but local state was interrupted

```php
use Mykholy\FailureLab\FailureLab;

$report = FailureLab::scenario('capture accepted, local commit interrupted')
    ->interruptOnceAt('provider.accepted')
    ->attempts(2) // total executions, including the first
    ->run(function ($checkpoint) use ($checkout): string {
        $reference = $checkout->captureUsingFakeGateway('order-42');
        $checkpoint->hit('provider.accepted'); // controlled test seam
        $checkout->markPaid('order-42', $reference);

        return $reference;
    });

$report
    ->assertAttempts(2)
    ->assertInterruptedAt('provider.accepted')
    ->assertFaultPlanSatisfied() // confirm every configured fault actually fired
    ->assertCheckpointHit('provider.accepted', 2)
    ->assertCompleted()
    ->assertInvariant('provider accepted once', fn (): bool => $fakeGateway->acceptedCaptures('order-42') === 1);

// Also assert the BUSINESS INVARIANT using the app's DB and fake provider:
$this->assertSame(1, $fakeGateway->acceptedCaptures('order-42'));
$this->assertSame('paid', Order::findOrFail(42)->status);
```

**Avoid false-green tests:** a scenario can complete successfully without ever
reaching a configured fault. Always pair `assertFaultPlanSatisfied()` with
`assertCompleted()` when your test is intended to prove recovery. The first
assertion verifies every configured checkpoint/attempt was actually interrupted;
the second verifies the operation eventually completed. Neither proves your
payment or database invariant by itself.

The fake gateway **must implement provider-side idempotency** for this assertion to be meaningful. FailureLab does not make an unsafe checkout safe; it exposes whether your integration handles the replay. Real external acceptance and unknown outcomes require reconciliation logic in your app.

## Example: the same webhook delivered three times

```php
FailureLab::replay('verified event evt-123')
    ->times(3)
    ->run(fn () => $webhookHandler->handle($sameVerifiedEvent))
    ->assertDeliveries(3)
    ->assertAllCompleted();

$this->assertDatabaseCount('ledger_entries', 1);
```

This is **sequential** replay, not a concurrent race test. Use your normal HTTP tests to check signature verification; use Race Lab or a process-isolated integration test to prove concurrency behavior.

### Example: failed delivery, then a successful retry

By default, the replay runner **stops after an unexpected exception**. To model a
transport sending the same message again *after a failed handler*, opt in:

```php
$report = FailureLab::replay('webhook failed then recovered')
    ->times(3)
    ->continueAfterFailure()
    ->run(function (int $delivery) use ($handler, $verifiedEvent): void {
        $handler->handle($verifiedEvent); // your application, using a disposable test DB
    });

$report->assertDeliveries(3)
    ->assertFailedDeliveries(1)
    ->assertRecoveredAfterFailure() // a later handler invocation succeeded
    ->assertInvariant('one committed ledger entry', fn (): bool => DB::table('ledger_entries')->count() === 1);
```

Configure your **test handler** to fail on a chosen delivery, e.g. throw inside a
database transaction the first time. FailureLab never injects an exception
into the replay callback automatically. A recovered handler call is **not**
proof of exactly-once delivery or correct financial state: use your own
`assertInvariant()` plus provider-fake assertions. Use the default stop-on-error
behavior when replaying potentially unsafe side effects.


After `run()`, `assertInvariant(name, predicate)` evaluates a project-specific recovery rule; failure messages include the rule's name only, not the values tested. `summary()` exposes attempt/checkpoint counters without leaking provider responses or arbitrary exception messages.

The canonical Composer package source now lives in this standalone repository. Its [incubation history](https://github.com/mykholy/mavvix-package-factory/tree/main/incubator/laravel-failurelab) is retained in the Mavvix Package Factory.

## Guarantees and limits

| It does | It does **not** |
| --- | --- |
| Simulate a specific exception on a named checkpoint/attempt | Kill a worker, process, container or network connection |
| Bound re-execution to at most 20 attempts | Retry arbitrary application exceptions |
| Record attempts, exact interrupted points and checked checkpoints | Record every side effect or guarantee idempotency |
| Sequentially replay a callback up to 20 times | Simulate true parallel transport or race conditions |
| Fail closed when `APP_ENV` is not `testing` | Make calls to a real external payment provider safe |
| Work with regular PHPUnit/Pest assertions | Depend on a daemon, production middleware or migration |

For edge cases, see [Limitations & threat model](docs/SAFETY.md), [Design decisions](docs/DESIGN.md), and [Runnable examples](examples/README.md).

## Release and distribution

The canonical source and CI live in this repository. Version 1.0.0 is available through [GitHub Releases](https://github.com/mykholy/laravel-failurelab/releases) and installable via [Packagist](https://packagist.org/packages/mykholy/laravel-failurelab). CI also verifies clean registry-based consumer installation.

### New: the handler committed, but the acknowledgement was lost

Available in the **next 1.1 release** (not in v1.0.0). A webhook handler can finish and
commit successfully, while a caller still retries because its acknowledgement was
lost. Model this explicitly, without throwing inside a successful handler:

```php
$report = FailureLab::replay('ack lost after webhook committed')
    ->times(3) // explicit simulated redelivery count; never automatic network retry
    ->loseAcknowledgementOn(1)
    ->run(fn () => $webhookHandler->handle($sameVerifiedEvent));

$report->assertDeliveries(3)
    ->assertAllCompleted() // each handler invocation returned successfully
    ->assertAcknowledgementPlanSatisfied()
    ->assertAcknowledgementLostOn(1)
    ->assertAcknowledgedAfterLoss()
    ->assertInvariant('one ledger entry', fn (): bool => DB::table('ledger_entries')->count() === 1);
```

Unlike `continueAfterFailure()`, this records **successful application execution**
with **simulated lost acknowledgement**. No network packet is dropped, no queue is
started, and no new delivery is scheduled automatically. The caller explicitly
sets `times()`. The report distinguishes attempted loss from actual loss,
so a callback that throws first cannot silently satisfy the loss plan.
The handler is responsible for idempotency and real database integrity.

## Developer workflow

```bash
composer install
composer test
composer analyse
vendor/bin/pint --test
```

Standalone GitHub Actions validates PHP 8.3–8.5, Laravel 12/13, PHPStan, Pint, real MySQL/PostgreSQL integration, and independent clean Laravel consumer installations. Passing CI is necessary but not sufficient for publishing a stable, verified Composer tag.

## Public API at a glance

| Method | Purpose |
| --- | --- |
| `FailureLab::scenario(string $name)` | Start an isolated failure scenario |
| `->interruptOnceAt(string $point, int $onAttempt = 1)` | Inject one `InjectedFailure` exception at one precise attempt |
| `->attempts(int $count)` | Set maximum total attempts (1–20) |
| `->run(callable $operation)` | Invoke operation, retrying only the package's own interruption |
| `->assertAttempts(int)` | Assert the number of actual invocations |
| `->assertInterruptedAt(string, int $times = 1)` | Assert exact fault activation count |
| `->assertCheckpointHit(string, int)` | Assert exact checkpoint count |
| `->assertCompleted()` / `->value()` | Check success and inspect returned value |
| `FailureLab::replay(...)->times(int)->run(callable)` | Run bounded sequential duplicate deliveries |
| `->loseAcknowledgementOn(int)` | Model acknowledgement lost **after successful handler** (v1.1) |
| `->assertAcknowledgementPlanSatisfied()` | Verify configured loss occurred after a completed callback (v1.1) |
| `->assertAcknowledgedAfterLoss()` | Require a later successful acknowledged delivery (v1.1) |

A `FailureLabManager` is available from Laravel's service container for dependency-injected tests; the static entry point needs no bootstrapping.

## Project status

**v1.0.0 — stable GitHub release**, with passing Laravel 12/13 tagged Composer consumer tests. FailureLab remains a test-only library: it does not simulate real process termination, concurrent workers, or distributed exactly-once delivery. [Release verification](docs/RELEASE.md) documents what was tested and what remains for Packagist.

MIT licensed. Contributions welcome; see [CONTRIBUTING.md](CONTRIBUTING.md). Maintained by [@mykholy](https://github.com/mykholy).