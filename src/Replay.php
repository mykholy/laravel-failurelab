<?php

declare(strict_types=1);

namespace Mykholy\FailureLab;

use InvalidArgumentException;
use Mykholy\FailureLab\Internal\EnvironmentGuard;
use Mykholy\FailureLab\Internal\Names;
use Throwable;

/** Sequential redelivery, not proof of parallel execution or transport exactly-once. */
final class Replay
{
    private int $deliveries = 2;

    private bool $continueAfterFailure = false;

    /** @var list<int> */
    private array $scheduledAcknowledgementLosses = [];

    public function __construct(public readonly string $name, private readonly EnvironmentGuard $guard)
    {
        Names::scenario($name);
    }

    public function times(int $deliveries): self
    {
        if ($deliveries < 1 || $deliveries > 20) {
            throw new InvalidArgumentException('Replay deliveries must be between 1 and 20.');
        }

        $next = clone $this;
        $next->deliveries = $deliveries;

        return $next;
    }

    /**
     * Continue with later sequential deliveries even if a handler throws.
     *
     * Opt-in only: callers must explicitly model retry-safe business behavior.
     * This is not a transport or queue retry, nor a real process restart.
     */
    public function continueAfterFailure(): self
    {
        $next = clone $this;
        $next->continueAfterFailure = true;

        return $next;
    }

    /**
     * Model a successful handler whose acknowledgement does not reach the caller.
     *
     * This is metadata for a bounded sequential test. It does not drop real
     * network packets, re-run the handler, or change the original callback.
     */
    public function loseAcknowledgementOn(int $delivery): self
    {
        if ($delivery < 1 || $delivery > 20) {
            throw new InvalidArgumentException('Acknowledgement-loss delivery must be between 1 and 20.');
        }

        if (in_array($delivery, $this->scheduledAcknowledgementLosses, true)) {
            throw new InvalidArgumentException('Duplicate acknowledgement-loss delivery.');
        }

        $next = clone $this;
        $next->scheduledAcknowledgementLosses[] = $delivery;

        return $next;
    }

    /** @param callable(int): mixed $handler */
    public function run(callable $handler): ReplayReport
    {
        $this->guard->assertTesting();

        foreach ($this->scheduledAcknowledgementLosses as $delivery) {
            if ($delivery > $this->deliveries) {
                throw new InvalidArgumentException('Acknowledgement-loss delivery exceeds configured replay count.');
            }
        }

        $attempts = [];
        $lostAcknowledgements = [];

        for ($delivery = 1; $delivery <= $this->deliveries; $delivery++) {
            try {
                $value = $handler($delivery);
                $attempts[] = new Attempt($delivery, 'completed', $value);

                // Acknowledgements can be lost only after a handler completed.
                if (in_array($delivery, $this->scheduledAcknowledgementLosses, true)) {
                    $lostAcknowledgements[] = $delivery;
                }
            } catch (Throwable $error) {
                $attempts[] = new Attempt($delivery, 'failed', error: $error);
                if (! $this->continueAfterFailure) {
                    // By default, stop on unknown side effects rather than replay blindly.
                    break;
                }
            }
        }

        return new ReplayReport($this->name, $attempts, $this->scheduledAcknowledgementLosses, $lostAcknowledgements);
    }
}
