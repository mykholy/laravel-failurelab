# Five-minute quick start

Use FailureLab in a **throwaway local or CI Laravel project**. The public API
example here works with the already tagged **v1.2.0**.

## Install

```bash
composer require --dev mykholy/laravel-failurelab:^1.2
```

Requirements: PHP 8.3+, Laravel 12/13 and `APP_ENV=testing` under CLI.
No queue, storage migration, external provider, or network account is required.

## Write an invariant-driven test

```php
use Mykholy\FailureLab\FailureLab;

public function test_recovery_after_a_named_interruption(): void
{
    $committedEffects = 0;

    $report = FailureLab::scenario('retry with a stable effect')
        ->interruptOnceAt('after.commit')
        ->attempts(2)
        ->run(static function ($checkpoint) use (&$committedEffects): string {
            // Simulates your host application's idempotent business write.
            $committedEffects = 1;
            $checkpoint->hit('after.commit');

            return 'recovered';
        });

    $report->assertAttempts(2)
        ->assertInterruptedAt('after.commit')
        ->assertFaultPlanSatisfied()
        ->assertCompleted()
        ->assertInvariant('exactly one committed effect', static fn (): bool => $committedEffects === 1);

    self::assertSame(1, $committedEffects);
}
```

This intentionally simple counter is a **teaching fixture**, not proof of
real DB atomicity. To verify rollback and commit boundaries, test against a
disposable database ([tested DB cases](../tests/Feature/DatabaseBoundaryTest.php)).

## Validate your test actually fails

Comment out `$checkpoint->hit('after.commit')`. Your test **must fail** at
`assertFaultPlanSatisfied()`. Then replace the stable effect with a deliberately
duplicated side effect and make the invariant fail. Without these negative
controls, a passing test can conceal a skipped failure injection.

## Next

- [Replay failed or duplicated webhooks](RECOVERY_PATTERNS.md)
- [Unknown provider outcomes](FAKE_PROVIDER.md) (v1.2.0+)
- [Real disposable queue worker SIGKILL recovery](REAL_WORKER_CRASH.md)
- [Safety and limits](SAFETY.md)
