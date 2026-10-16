<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Testing;

use InvalidArgumentException;
use Mykholy\FailureLab\Internal\EnvironmentGuard;
use Mykholy\FailureLab\Testing\Exceptions\SimulatedUnknownOutcome;

/**
 * In-memory fake provider for isolated tests, never a payment integration.
 *
 * Calls with the same key have only one synthetic accepted capture. This is
 * deliberately application-independent and never opens network connections.
 */
final class FakeCaptureGateway
{
    /** @var array<string, string> */
    private array $accepted = [];

    private int $requests = 0;

    private readonly EnvironmentGuard $guard;

    public function __construct(?EnvironmentGuard $guard = null)
    {
        $this->guard = $guard ?? new EnvironmentGuard;
    }

    /**
     * @throws SimulatedUnknownOutcome When the fake accepted a capture but the caller received no response.
     */
    public function capture(string $idempotencyKey, bool $loseResponse = false): string
    {
        $this->guard->assertTesting();
        $this->assertValidKey($idempotencyKey);

        $this->requests++;

        if (! isset($this->accepted[$idempotencyKey])) {
            $this->accepted[$idempotencyKey] = 'fake_capture_'.substr(hash('sha256', $idempotencyKey), 0, 24);
        }

        if ($loseResponse) {
            throw new SimulatedUnknownOutcome;
        }

        return $this->accepted[$idempotencyKey];
    }

    /** Read-only fake reconciliation by the same idempotency identity. */
    public function lookup(string $idempotencyKey): ?string
    {
        $this->guard->assertTesting();
        $this->assertValidKey($idempotencyKey);

        return $this->accepted[$idempotencyKey] ?? null;
    }

    /** Number of unique fake captures accepted, not total requests. */
    public function acceptedCount(): int
    {
        $this->guard->assertTesting();

        return count($this->accepted);
    }

    /** Number of attempted fake capture calls, including lost responses. */
    public function requestCount(): int
    {
        $this->guard->assertTesting();

        return $this->requests;
    }

    private function assertValidKey(string $key): void
    {
        if ($key === '' || strlen($key) > 128 || trim($key) !== $key || preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            throw new InvalidArgumentException('Fake capture idempotency key must be 1-128 non-control bytes with no surrounding whitespace.');
        }
    }
}
