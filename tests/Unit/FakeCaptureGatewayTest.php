<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Tests\Unit;

use InvalidArgumentException;
use Mykholy\FailureLab\Exceptions\UnsafeEnvironment;
use Mykholy\FailureLab\FailureLab;
use Mykholy\FailureLab\Testing\Exceptions\SimulatedUnknownOutcome;
use Mykholy\FailureLab\Testing\FakeCaptureGateway;
use PHPUnit\Framework\TestCase;

final class FakeCaptureGatewayTest extends TestCase
{
    public function test_same_key_is_accepted_only_once_even_after_synthetic_unknown_outcome(): void
    {
        $gateway = new FakeCaptureGateway;

        try {
            $gateway->capture('order-42-capture', loseResponse: true);
            self::fail('Expected simulated unknown outcome.');
        } catch (SimulatedUnknownOutcome $e) {
            self::assertStringNotContainsString('order-42', $e->getMessage());
            self::assertStringNotContainsString('sk_test_', $e->getMessage());
        }

        $reconciled = $gateway->lookup('order-42-capture');
        self::assertNotNull($reconciled);

        $reference = $gateway->capture('order-42-capture');
        self::assertSame($reconciled, $reference);
        self::assertSame(1, $gateway->acceptedCount());
        self::assertSame(2, $gateway->requestCount());
        self::assertNull($gateway->lookup('unknown-key'));
    }

    public function test_replay_can_prove_one_fake_external_acceptance_across_recovery(): void
    {
        $gateway = new FakeCaptureGateway;

        $report = FailureLab::scenario('fake provider accepted before test interruption')
            ->interruptOnceAt('provider.accepted')
            ->attempts(2)
            ->run(static function ($checkpoint) use ($gateway): string {
                $reference = $gateway->capture('stable-payment-id');
                $checkpoint->hit('provider.accepted');

                return $reference;
            });

        $report->assertAttempts(2)
            ->assertFaultPlanSatisfied()
            ->assertCompleted()
            ->assertInvariant('one accepted capture across retries', static fn (): bool => $gateway->acceptedCount() === 1);

        self::assertSame(2, $gateway->requestCount());
        self::assertSame(1, $gateway->acceptedCount());
    }

    public function test_changed_idempotency_key_exposes_the_duplicate_capture_bug(): void
    {
        $gateway = new FakeCaptureGateway;
        $gateway->capture('capture-attempt-1');
        $gateway->capture('capture-attempt-2');

        self::assertSame(2, $gateway->acceptedCount());
        self::assertNotSame($gateway->lookup('capture-attempt-1'), $gateway->lookup('capture-attempt-2'));
    }

    public function test_key_validation_rejects_unsafe_or_empty_inputs(): void
    {
        foreach (['', ' bad', "bad\nkey", str_repeat('a', 129)] as $key) {
            try {
                (new FakeCaptureGateway)->capture($key);
                self::fail('Expected an invalid fake idempotency key.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_fake_gateway_refuses_non_testing_environment(): void
    {
        $previous = getenv('APP_ENV');

        try {
            putenv('APP_ENV=production');

            $this->expectException(UnsafeEnvironment::class);
            (new FakeCaptureGateway)->capture('will-never-capture');
        } finally {
            if ($previous === false) {
                putenv('APP_ENV');
            } else {
                putenv('APP_ENV='.$previous);
            }
        }
    }
}
