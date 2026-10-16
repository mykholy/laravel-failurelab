<?php

declare(strict_types=1);

/**
 * Linux-only controlled SIGKILL drill, executed in a THROWAWAY Laravel host.
 * Terminates only proc_open() children owned by this PHP test process.
 */
use App\Jobs\FailureLabCrashJob;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Linux' || getenv('APP_ENV') !== 'testing') {
    throw new RuntimeException('Only Linux CLI and APP_ENV=testing can run this crash drill.');
}

if (! extension_loaded('pdo_sqlite') || ! function_exists('proc_open') || ! function_exists('proc_terminate')) {
    throw new RuntimeException('Linux process support and pdo_sqlite are required.');
}

$directory = sys_get_temp_dir().'/failurelab-disposable-'.bin2hex(random_bytes(8));

if (! mkdir($directory, 0700) || ! is_dir($directory)) {
    throw new RuntimeException('Cannot create disposable test directory.');
}

$database = $directory.'/queue.sqlite';
$marker = $directory.'/committed.marker';
$first = null;
$second = null;
$stdout = $directory.'/worker.stdout';
$stderr = $directory.'/worker.stderr';

$environment = array_merge(getenv(), [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $database,
    'QUEUE_CONNECTION' => 'database',
    'DB_QUEUE_RETRY_AFTER' => '1',
    'FAILURELAB_CRASH_FIXTURE' => 'yes',
    'FAILURELAB_CRASH_MARKER' => $marker,
]);

$startWorker = static function () use ($environment, $stdout, $stderr) {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['file', $stdout, 'a'],
        2 => ['file', $stderr, 'a'],
    ];
    $command = [PHP_BINARY, 'artisan', 'queue:work', 'database', '--queue=default', '--stop-when-empty', '--sleep=0', '--tries=3', '--timeout=0'];

    $process = proc_open($command, $descriptors, $pipes, getcwd(), $environment);

    if (! is_resource($process)) {
        throw new RuntimeException('Cannot create isolated Laravel worker.');
    }

    fclose($pipes[0]);

    return $process;
};

$await = static function (callable $predicate, float $timeoutSeconds): bool {
    $deadline = microtime(true) + $timeoutSeconds;

    do {
        if ($predicate()) {
            return true;
        }

        usleep(50_000);
    } while (microtime(true) < $deadline);

    return false;
};

try {
    if (! touch($database)) {
        throw new RuntimeException('Cannot create disposable SQLite fixture.');
    }

    putenv('DB_CONNECTION=sqlite');
    putenv('DB_DATABASE='.$database);
    putenv('QUEUE_CONNECTION=database');
    putenv('DB_QUEUE_RETRY_AFTER=1');
    putenv('FAILURELAB_CRASH_FIXTURE=yes');
    putenv('FAILURELAB_CRASH_MARKER='.$marker);

    require getcwd().'/vendor/autoload.php';
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    // The process is allowed to touch ONLY the new disposable SQLite database.
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', $database);
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.retry_after', 1);
    DB::purge('sqlite');

    Schema::create('jobs', static function (Blueprint $table): void {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    Schema::create('failurelab_ledger', static function (Blueprint $table): void {
        $table->id();
        $table->string('event_key')->unique();
    });
    Schema::create('failurelab_completion', static function (Blueprint $table): void {
        $table->id();
        $table->string('event_key')->unique();
        $table->unsignedInteger('attempt');
    });

    Queue::connection('database')->push(new FailureLabCrashJob('demo-event'));

    $first = $startWorker();
    if (! $await(static fn (): bool => is_file($marker), 12)) {
        throw new RuntimeException('The first worker never committed its checkpoint.');
    }
    if (! proc_get_status($first)['running']) {
        throw new RuntimeException('Worker already exited before requested SIGKILL.');
    }

    if (! proc_terminate($first, 9)) {
        throw new RuntimeException('Unable to SIGKILL the owned fixture worker.');
    }

    if (! $await(static fn (): bool => ! proc_get_status($first)['running'], 5)) {
        throw new RuntimeException('Fixture worker did not terminate.');
    }

    $status = proc_get_status($first);
    if (! $status['signaled'] || $status['termsig'] !== 9) {
        throw new RuntimeException('Expected genuine SIGKILL, not an ordinary exception.');
    }

    if (DB::table('failurelab_ledger')->count() !== 1 || DB::table('failurelab_completion')->count() !== 0) {
        throw new RuntimeException('Crash fixture did not retain precisely one committed ledger effect.');
    }

    // DB jobs become eligible once their short lease expires. This is real
    // Laravel database-queue reservation behavior, not FailureLab's replay loop.
    sleep(2);
    $second = $startWorker();

    if (! $await(static fn (): bool => ! proc_get_status($second)['running'], 15)) {
        throw new RuntimeException('Recovery worker did not exit in the bounded window.');
    }

    $effects = DB::table('failurelab_ledger')->count();
    $completed = DB::table('failurelab_completion')->first();
    $pending = DB::table('jobs')->count();

    if ($effects !== 1 || $pending !== 0 || $completed === null || $completed->attempt !== 2) {
        throw new RuntimeException('Crash recovery invariant failed: duplicate effect, missing completion or incorrect retry.');
    }

    echo "PASS: real SIGKILL, committed SQLite effect, queued retry on a fresh Laravel worker, exactly one ledger row\n";
} finally {
    foreach ([$first, $second] as $process) {
        if (! is_resource($process)) {
            continue;
        }
        if (proc_get_status($process)['running']) {
            proc_terminate($process, 9);
        }
        proc_close($process);
    }

    foreach ([$marker, $database, $database.'-wal', $database.'-shm', $stdout, $stderr] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    rmdir($directory);
}
