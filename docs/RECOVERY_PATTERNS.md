# Recovery patterns and negative controls

FailureLab is best used to answer a precise question: **what would remain
committed if this one step were interrupted, retried or redelivered?**

| Pattern | Test runner | Required app invariant |
| --- | --- | --- |
| Fault after fake provider acceptance | `Scenario::interruptOnceAt(...)` | One accepted provider operation by stable idempotency key |
| Exception before DB transaction commits | `Scenario` with DB rollback fixture | One committed row after retry |
| Handler fails; later delivery succeeds | `Replay::continueAfterFailure()` | One durable ledger event and eventual completion |
| Handler commits; acknowledgement is lost | `Replay::loseAcknowledgementOn(1)` | The same event is not applied twice |
| Database queue worker is SIGKILLed | [Real worker crash drill](REAL_WORKER_CRASH.md) | Lease expiry, resumed job, only one committed effect |
| Provider response unknown | [Fake provider](FAKE_PROVIDER.md) | Reconciliation resolves known acceptance; no blind capture retry |

## The five assertions to know

```php
$report->assertAttempts(2);
$report->assertFaultPlanSatisfied();   // prevents skipped-fault false positives
$report->assertInterruptedAt('provider.accepted');
$report->assertCompleted();
$report->assertInvariant('one business effect', fn (): bool => $effects === 1);
```

The last assertion is **your** application's invariant, not one supplied by
the package. A completion assertion alone proves no idempotency.

## Acknowledgement lost after a successful handler (v1.1.0)

```php
$report = FailureLab::replay('acknowledgement lost')
    ->times(3)
    ->loseAcknowledgementOn(1)
    ->run(fn () => $webhookHandler->handle($sameVerifiedEvent));

$report->assertAllCompleted()
    ->assertAcknowledgementPlanSatisfied()
    ->assertAcknowledgedAfterLoss()
    ->assertInvariant('one durable event', fn (): bool => DB::table('ledger_entries')->count() === 1);
```

No packet is dropped and no transport retry is scheduled. The test controls
the number of callback executions; the host handler controls deduplication.

## Negative controls to run

1. Remove the configured checkpoint and prove the test fails.
2. Make a fake provider accept the **same logical operation under two different
   idempotency keys** and prove your invariant fails.
3. Make the first replay delivery throw, so an acknowledgement loss planned for
   that delivery is **not** counted.
4. Simulate a denied rollback or failed final state; `assertCompleted()`
   must be checked separately from the fault plan.
5. Never use real credentials, payment cards, live webhooks, or production
   databases in these fixtures.

[Public APIs and trust boundaries](DESIGN.md) · [Safety](SAFETY.md) ·
[Provider fixture](FAKE_PROVIDER.md)
