# Real Laravel worker SIGKILL and recovery drill

**Experimental integration fixture (not part of the published v1.1.0 API).**
This independent CI drill starts a real Laravel database queue worker in a
**throwaway** Laravel host, waits for an SQLite ledger transaction to commit,
then sends **SIGKILL only to that subprocess**, allows its DB queue lease to
expire, and starts another worker to finish the job.

It proves that **the application fixture's unique event key** prevents a
duplicate ledger effect despite a real abrupt worker exit. It does **not**
claim that FailureLab's in-process checkpoint API kills processes or that all
Laravel queue drivers/providers behave the same way.

## Execution requirements

Linux CLI with PHP 8.3+, SQLite PDO, and `proc_open` / `proc_terminate`.
CI runs this only against clean disposable Laravel 12/13 apps. The test creates
a unique temporary SQLite database under `sys_get_temp_dir()`; it never
connects to an existing database or launches production queues.

The runner is intentionally not callable without `APP_ENV=testing`.
The queued fixture separately requires `FAILURELAB_CRASH_FIXTURE=yes`.
Both workers are children of the test process, with bounded waits and cleanup.
This is a **real process failure**, not a network partition, a system OOM, or
proof of exactly-once processing.

## What CI checks

1. A genuine Linux SIGKILL terminates a disposable queue worker after commit.
2. The committed ledger row survives; the job is not marked completed.
3. The queue reservation expires; a second worker executes attempt two.
4. Exactly one ledger effect exists, one completion is written, and the queue
   is empty after recovery.

See `tests/Process/queue-recovery.php` and
`tests/Process/FailureLabCrashJob.php`. The latter is a test fixture copied
to a clean Laravel host and must **never** be copied into production.
