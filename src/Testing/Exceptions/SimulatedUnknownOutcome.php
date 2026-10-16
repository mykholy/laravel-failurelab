<?php

declare(strict_types=1);

namespace Mykholy\FailureLab\Testing\Exceptions;

use RuntimeException;

/**
 * The synthetic provider has accepted the operation; its response is unknown.
 * This exception intentionally contains no caller-specific data or secrets.
 */
final class SimulatedUnknownOutcome extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Simulated provider response was lost after acceptance; reconcile before retrying.');
    }
}
