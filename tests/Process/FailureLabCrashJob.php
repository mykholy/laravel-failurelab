<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A deliberately crashing, disposable queue fixture. Never ship in an app.
 * Copied into a throwaway Laravel project by FailureLab's CI.
 */
final class FailureLabCrashJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public string $eventKey) {}

    public function handle(): void
    {
        if (getenv('APP_ENV') !== 'testing' || getenv('FAILURELAB_CRASH_FIXTURE') !== 'yes') {
            throw new RuntimeException('Refusing to execute outside an isolated test fixture.');
        }

        // The application, not FailureLab, owns this idempotency guarantee.
        DB::table('failurelab_ledger')->insertOrIgnore(['event_key' => $this->eventKey]);

        if ($this->attempts() === 1) {
            $marker = getenv('FAILURELAB_CRASH_MARKER');
            if (! is_string($marker) || $marker === '') {
                throw new RuntimeException('Missing crash synchronization marker.');
            }

            // The ledger commit has completed before the parent kills this worker.
            if (file_put_contents($marker, 'committed') === false) {
                throw new RuntimeException('Unable to record committed test checkpoint.');
            }

            sleep(30);
        }

        DB::table('failurelab_completion')->insert([
            'event_key' => $this->eventKey,
            'attempt' => $this->attempts(),
        ]);
    }
}
