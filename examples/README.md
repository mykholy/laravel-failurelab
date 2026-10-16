# Runnable local demonstration

The standalone demo runs under PHP 8.3+ without Laravel dependencies. It tests the fault/replay engine only, with an in-memory fake gateway and in-memory persistence. It **does not** validate Laravel integration, processes, database commits, locks, queues, or a real payment gateway.

```bash
APP_ENV=testing php examples/payment-demo.php
```

For Laravel integration examples using disposable SQLite tables, see `tests/Feature/CheckoutRecoveryTest.php` and `tests/Feature/WebhookReplayTest.php` (require `composer install`, Testbench and PHPUnit).


## Choose the right recovery exercise

| Case | Verified example |
| --- | --- |
| In-process named interruption | `examples/payment-demo.php` |
| Real Laravel commit boundary | `tests/Feature/DatabaseBoundaryTest.php` |
| Synthetic unknown provider response / stable idempotency key | [Fake provider guide](../docs/FAKE_PROVIDER.md) (unreleased candidate) |
| Real worker SIGKILL after commit followed by database queue recovery | [Linux crash drill](../docs/REAL_WORKER_CRASH.md) (separate CI fixture) |

Start with [the documentation index](../docs/README.md) and keep every test
environment pointed at temporary or disposable data.
