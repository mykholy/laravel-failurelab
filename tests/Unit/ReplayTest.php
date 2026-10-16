<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Tests\Unit;

use InvalidArgumentException;
use Mykholy\FailureLab\Exceptions\ExpectationFailed;
use Mykholy\FailureLab\FailureLab;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReplayTest extends TestCase
{
    public function test_sequential_redelivery_can_be_asserted_without_duplicate_local_effects(): void
    {
        $seen = [];
        $effects = 0;

        $report = FailureLab::replay('webhook-42')->times(3)->run(function () use (&$seen, &$effects) {
            if (! isset($seen['webhook-42'])) {
                $seen['webhook-42'] = true;
                $effects++;
            }
        });

        $report->assertDeliveries(3)->assertAllCompleted();
        self::assertSame(1, $effects);
    }

    public function test_real_failure_stops_further_deliveries(): void
    {
        $report = FailureLab::replay('real error')->times(3)
            ->run(fn () => throw new RuntimeException('database unavailable'));
        $report->assertDeliveries(1);
        $this->expectException(ExpectationFailed::class);
        $report->assertAllCompleted();
    }

    public function test_opt_in_redelivery_can_recover_from_a_transient_handler_failure(): void
    {
        $calls = 0;
        $localEffects = 0;

        $report = FailureLab::replay('temporary delivery failure')
            ->times(3)
            ->continueAfterFailure()
            ->run(static function (int $delivery) use (&$calls, &$localEffects): void {
                $calls++;
                if ($delivery === 1) {
                    throw new RuntimeException('private-provider-error-detail');
                }
                // Host-controlled duplicate suppression, not provided by FailureLab.
                $localEffects = 1;
            });

        self::assertSame(3, $calls);
        $report->assertDeliveries(3)
            ->assertFailedDeliveries(1)
            ->assertRecoveredAfterFailure()
            ->assertInvariant('one local business effect', static fn (): bool => $localEffects === 1);

        self::assertSame(
            ['scenario' => 'temporary delivery failure', 'deliveries' => 3, 'completed' => 2, 'failed' => 1],
            $report->summary()
        );
        self::assertSame('failed', $report->deliveries[0]->status);
        self::assertSame('completed', $report->deliveries[1]->status);
        self::assertSame('completed', $report->deliveries[2]->status);
    }

    public function test_continue_after_failure_builder_does_not_mutate_default_fail_closed_behavior(): void
    {
        $base = FailureLab::replay('safe default')->times(3);
        $continuing = $base->continueAfterFailure();
        self::assertNotSame($base, $continuing);

        $callback = static fn (int $delivery): int => $delivery === 1
            ? throw new RuntimeException('temporary')
            : $delivery;

        $base->run($callback)->assertDeliveries(1)->assertFailedDeliveries(1);
        $continuing->run($callback)->assertDeliveries(3)->assertRecoveredAfterFailure();
    }

    public function test_recovery_assertion_does_not_pass_if_all_deliveries_fail(): void
    {
        $report = FailureLab::replay('never recovered')
            ->times(3)
            ->continueAfterFailure()
            ->run(static fn (): never => throw new RuntimeException('sensitive-failure'));
        $report->assertFailedDeliveries(3);

        $this->expectException(ExpectationFailed::class);
        $this->expectExceptionMessage('has no completed handler delivery');
        $report->assertRecoveredAfterFailure();
    }

    public function test_recovery_assertion_requires_an_actual_failure(): void
    {
        $report = FailureLab::replay('healthy delivery')
            ->times(3)->continueAfterFailure()->run(static fn (): bool => true);
        $report->assertFailedDeliveries(0);

        $this->expectException(ExpectationFailed::class);
        $report->assertRecoveredAfterFailure();
    }

    public function test_asserting_failure_count_is_precise(): void
    {
        $report = FailureLab::replay('bounded failure')
            ->times(2)->continueAfterFailure()->run(
                static fn (int $delivery) => $delivery === 1 ? throw new RuntimeException('secret') : true,
            );

        $this->expectException(ExpectationFailed::class);
        $this->expectExceptionMessage('expected 2 failed deliveries; got 1.');
        $report->assertFailedDeliveries(2);
    }

    public function test_replay_name_rejects_bidirectional_formatting_controls(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FailureLab::replay("report\u{202E}spoof");
    }


    public function test_acknowledgement_loss_records_completed_handler_then_later_acknowledgement(): void
    {
        $handlerCalls = 0;
        $effects = 0;

        $report = FailureLab::replay('acknowledgement disappeared after commit')
            ->times(3)
            ->loseAcknowledgementOn(1)
            ->run(static function (int $delivery) use (&$handlerCalls, &$effects): string {
                $handlerCalls++;
                // Fake application deduplicates the event on its own.
                $effects = 1;

                return 'handled-'.$delivery;
            });

        self::assertSame(3, $handlerCalls);
        self::assertSame(1, $effects);
        self::assertSame([1], $report->lostAcknowledgements);
        $report->assertDeliveries(3)
            ->assertAllCompleted()
            ->assertFailedDeliveries(0)
            ->assertAcknowledgementPlanSatisfied()
            ->assertAcknowledgementLostOn(1)
            ->assertAcknowledgedAfterLoss()
            ->assertInvariant('one effect after duplicate delivery', static fn (): bool => $effects === 1);
        self::assertSame('handled-1', $report->deliveries[0]->value);
    }

    public function test_acknowledgement_loss_does_not_mutate_replay_builder_or_default_report(): void
    {
        $base = FailureLab::replay('no loss by default')->times(2);
        $altered = $base->loseAcknowledgementOn(1);

        self::assertNotSame($base, $altered);
        self::assertSame([], $base->run(static fn (): bool => true)->lostAcknowledgements);
        self::assertSame([1], $altered->run(static fn (): bool => true)->lostAcknowledgements);
    }

    public function test_handler_failure_does_not_falsely_satisfy_acknowledgement_plan(): void
    {
        $report = FailureLab::replay('handler failed before its acknowledgement')
            ->times(2)
            ->loseAcknowledgementOn(1)
            ->continueAfterFailure()
            ->run(static fn (int $n): bool => $n === 1
                ? throw new RuntimeException('private webhook exception payload')
                : true);

        self::assertSame([], $report->lostAcknowledgements);
        $report->assertFailedDeliveries(1);

        $this->expectException(ExpectationFailed::class);
        $this->expectExceptionMessage('did not reach planned acknowledgement loss');
        $report->assertAcknowledgementPlanSatisfied();
    }

    public function test_last_delivery_loss_is_not_mistaken_for_recovered_acknowledgement(): void
    {
        $report = FailureLab::replay('no later acknowledgement')
            ->times(2)
            ->loseAcknowledgementOn(2)
            ->run(static fn (): bool => true);

        $report->assertAcknowledgementPlanSatisfied()->assertAcknowledgementLostOn(2);

        $this->expectException(ExpectationFailed::class);
        $report->assertAcknowledgedAfterLoss();
    }

    public function test_unplanned_acknowledgement_loss_does_not_pass_verification(): void
    {
        $report = FailureLab::replay('no scheduled acknowledgement losses')
            ->times(2)->run(static fn (): bool => true);
        self::assertSame([], $report->lostAcknowledgements);

        $this->expectException(ExpectationFailed::class);
        $report->assertAcknowledgementPlanSatisfied();
    }

    public function test_replay_rejects_duplicate_acknowledgement_loss_deliveries(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FailureLab::replay('duplicate loss')->loseAcknowledgementOn(1)->loseAcknowledgementOn(1);
    }

    public function test_replay_rejects_acknowledgement_loss_after_bounded_deliveries(): void
    {
        $ran = false;
        $scenario = FailureLab::replay('out of range')
            ->loseAcknowledgementOn(3)
            ->times(2);

        try {
            $scenario->run(static function () use (&$ran): void {
                $ran = true;
            });

            self::fail('Expected planned loss outside delivery bound to throw.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('exceeds configured replay count', $e->getMessage());
            self::assertFalse($ran);
        }
    }

    public function test_replay_rejects_zero_acknowledgement_loss_index(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FailureLab::replay('invalid loss')->loseAcknowledgementOn(0);
    }

    public function test_acknowledged_recovery_requires_a_later_acknowledged_success(): void
    {
        $report = FailureLab::replay('all acknowledgements lost')
            ->times(2)
            ->loseAcknowledgementOn(1)
            ->loseAcknowledgementOn(2)
            ->run(static fn (): bool => true);

        $report->assertAcknowledgementPlanSatisfied();
        self::assertSame([1, 2], $report->lostAcknowledgements);

        $this->expectException(ExpectationFailed::class);
        $report->assertAcknowledgedAfterLoss();
    }

    public function test_invalid_delivery_counts_fail_early(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FailureLab::replay('safe')->times(21);
    }
}
