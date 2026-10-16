# Test-only fake provider: idempotency and unknown outcomes

**Added in v1.2.0** (check that the tagged release is published before installing; not available in v1.1.0).
`Mykholy\FailureLab\Testing\FakeCaptureGateway` is a **purely in-memory fake**.
It makes **zero HTTP requests** and stores no real payments. Calls sharing an
idempotency key return the same deterministic synthetic capture reference.

The fake reproduces a common failure boundary: the provider has accepted a
capture, but the application **cannot observe the response**.

## Example: reconcile an unknown response

```php
use Mykholy\FailureLab\Testing\Exceptions\SimulatedUnknownOutcome;
use Mykholy\FailureLab\Testing\FakeCaptureGateway;

$provider = new FakeCaptureGateway;
$key = 'test-order-42-capture';

try {
    $provider->capture($key, loseResponse: true);
} catch (SimulatedUnknownOutcome) {
    // IMPORTANT: a timeout does not prove that capture failed.
    $reference = $provider->lookup($key);
    $this->assertNotNull($reference);
}

$this->assertSame($reference, $provider->capture($key));
$this->assertSame(1, $provider->acceptedCount());
$this->assertSame(2, $provider->requestCount());
```

Always use **one stable key** for one intended operation. To make the test fail
on an unsafe host integration, deliberately compare against a negative control
using a new key; the fake will correctly show two accepted captures.

## Example: controlled post-acceptance interruption

```php
$gateway = new FakeCaptureGateway;

$report = FailureLab::scenario('gateway accepted, application interrupted')
    ->interruptOnceAt('provider.accepted')
    ->attempts(2)
    ->run(static function ($checkpoint) use ($gateway): string {
        $ref = $gateway->capture('same-order-same-key');
        $checkpoint->hit('provider.accepted');

        return $ref;
    });

$report->assertFaultPlanSatisfied()->assertCompleted()
    ->assertInvariant('one remote acceptance', fn (): bool => $gateway->acceptedCount() === 1);
```

## Guarantees and limits

- **Test-only:** requires Linux/macOS/Windows CLI with exactly `APP_ENV=testing`.
- **Memory only:** no persistent or cross-process idempotency. For real worker
  termination see the separate [SIGKILL drill](REAL_WORKER_CRASH.md), which uses
  a temporary database rather than this fake.
- **No real transport:** response loss is a deliberate exception emitted
  *after* the fake accepted the request.
- **No provider-specific correctness:** production calls still need provider
  idempotency support, read-only reconciliation, auth and timeout rules.
- No secrets, credentials, card information, real merchant IDs or actual
  provider responses are used or exposed in assertion messages.
