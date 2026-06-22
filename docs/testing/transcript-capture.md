# Test transcript capture — usage guide

Every acceptance run captures the full stream of messages between PHP workers and RoadRunner, plus PSR-3 logs, exceptions (including retried ones), the workflow history of every test, and a `[FATAL]` line if a worker crashes. Open one file after the run and you see everything.

---

## TL;DR

```bash
composer test:accept-fast              # run any acceptance suite
composer test:transcript:last          # merge per-process shards into one file
less runtime/tests/transcripts/_merged/transcript.log
```

If a test fails, PHPUnit also prints the path to stderr:

```
[transcript] /Users/.../runtime/tests/transcripts/phpunit__pid32098__1778699827739.log
[transcript] run composer test:transcript:last to view the merged stream
```

---

## Where the files live

All transcripts go under one directory at the project root:

```
sdk-php/
└── runtime/
    └── tests/
        └── transcripts/                            # ← all artifacts live here
            ├── phpunit__pid<pid>__<startMs>.log    # PHPUnit process writes here:
            │                                       #   [TEST_START], [TEST_END], [HISTORY], [HISTORY_ERROR]
            ├── worker__pid<pid>__<startMs>.log     # Each PHP worker process spawned by RR:
            │                                       #   [WIRE_INBOUND], [WIRE_OUTBOUND], [LOG],
            │                                       #   [EXCEPTION], [ERROR], [FATAL], [META]
            ├── worker__pid<otherPid>__...log       # One file per worker PID (default num_workers=2)
            └── _merged/
                └── transcript.log                  # ← single chronologically-merged view,
                                                    #   produced by composer test:transcript:last
```

Filename anatomy:

- **`phpunit__`** — the PHPUnit (test client) process. One file per `composer test:*` run.
- **`worker__`** — a PHP worker process. RoadRunner runs N workers (controlled by `tests/Acceptance/.rr.yaml:activities.num_workers`), so you'll typically see one `worker__*` file per active worker. RoadRunner respawns crashed workers, and each respawn gets a fresh PID → fresh file (the old PID file is left intact for forensics).
- **`pid<N>__<startMs>`** — uniquifies files when PIDs are reused across runs.

> The directory is **wiped at the START of every acceptance run** (see `tests/Acceptance/bootstrap.php`). That's intentional: the artifact reflects the most recent run only. If you need to preserve it, copy it elsewhere immediately after the run.

Configurable via env var: set `TEMPORAL_TRANSCRIPT_DIR=/some/path` (absolute or repo-relative) to change the destination.

---

## How to use it

### 1. Run any acceptance test as usual

```bash
composer test:accept             # full suite
composer test:accept-fast        # fast tier
composer test:accept-slow        # slow tier
# or:
vendor/bin/phpunit --testsuite=Acceptance --filter='TranscriptHappyPath'
```

Capture is **on by default** for acceptance (`tests/Acceptance/.rr.yaml` sets `TEMPORAL_WIRE_TRACE: "1"`). No action needed. The merge file is NOT auto-generated.

### 2. Merge the per-process shards into one file

```bash
composer test:transcript:last
```

This script (`bin/transcript-merge.php`):
- Reads every `*.log` file under `runtime/tests/transcripts/`,
- Sorts every line by `(timestamp, pid, sequence)`,
- Writes the result to `runtime/tests/transcripts/_merged/transcript.log`,
- Prints summary stats to stderr (wire frame counts, history line counts, fatal line counts).

You can also run it manually:

```bash
php bin/transcript-merge.php
```

### 3. Read or grep the merged file

```bash
less runtime/tests/transcripts/_merged/transcript.log
```

Or jump straight to what you care about — see [Common queries](#common-queries) below.

### 4. Clean up manually (optional)

```bash
composer clean:transcripts
```

Removes every file under `runtime/tests/transcripts/`. Normally not needed — the next acceptance run does this automatically.

---

## What you'll see in the file

Every line looks like this:

```
2026-05-13T19:17:17.334416+00:00 32150 20 [WIRE_INBOUND] frame_id=7 bytes=200 payload={"headers":{...},"body":{...}}
└─── ISO8601 timestamp ─────────┘ │     │   │           └─── attributes ─────┘ └── optional JSON payload ─────────┘
                                  │     │   └── section tag
                                  │     └── per-PID monotonic sequence
                                  └── PID of the producing process
```

Section tags you'll encounter:

| Section | Source | What it means |
|---|---|---|
| `META` | every process | Lifecycle markers — writer init, fatal-handler registered, host recording started, activity/workflow entry+completion |
| `TEST_START` / `TEST_END` | PHPUnit | Test boundary. `TEST_END` carries `status=passed/failed/skipped`, `duration_ms`, `exception_class` |
| `LOG` | worker | `Workflow::getLogger()->info(...)` and any SDK PSR-3 log call |
| `WIRE_INBOUND` | worker | Every frame the worker received FROM RoadRunner (server-driven workflow tick, activity invocation, signal delivery, ...) |
| `WIRE_OUTBOUND` | worker | Every frame the worker SENT to RoadRunner (workflow command, activity response, completion, ...) — `frame_id` matches the corresponding `WIRE_INBOUND` |
| `EXCEPTION` | worker | An activity/workflow/nexus call threw. `phase` and `attempt` tell you which one. Retries produce one line per attempt. |
| `ERROR` | worker | PHP `E_WARNING`, `E_NOTICE`, etc., trapped by `set_error_handler` (does not stop execution) |
| `FATAL` | either | Worker died with a fatal error or uncaught throwable. The transcript still has every earlier line. |
| `HISTORY` | PHPUnit | One line per `HistoryEvent` from the workflow execution the test touched. `event_type` like `EVENT_TYPE_WORKFLOW_EXECUTION_STARTED`, `EVENT_TYPE_ACTIVITY_TASK_FAILED`, etc. |
| `HISTORY_ERROR` | PHPUnit | History fetch itself failed (the test result is unaffected) |
| `WIRE_ERROR` | worker | RoadRunner-level transport error from the worker side |
| `TRUNCATED` | worker | The writer hit its 50 MB soft cap and rotated |

A more detailed schema reference is in [`Section reference`](#section-reference) at the bottom of this page.

---

## Common queries

```bash
# Just opened the file: how big is the merged stream and what does it contain?
composer test:transcript:last     # prints counts to stderr

# Show every line for one specific test
grep -nE '\[(TEST_START|TEST_END|EXCEPTION|FATAL|HISTORY)\].*method=testHappyPathRoundTripIsCaptured' \
  runtime/tests/transcripts/_merged/transcript.log

# Find every activity retry across the run
grep '\[EXCEPTION\].*phase=activity_throw' \
  runtime/tests/transcripts/_merged/transcript.log

# How many times did each activity retry?
grep '\[EXCEPTION\].*phase=activity_throw' \
  runtime/tests/transcripts/_merged/transcript.log \
  | grep -oE 'name=\S+|attempt=\d+'

# Workflow history for a specific workflow id
grep '\[HISTORY\].*workflow_id=01957d4f-' \
  runtime/tests/transcripts/_merged/transcript.log

# Did any worker process crash?
grep '\[FATAL\]' runtime/tests/transcripts/_merged/transcript.log \
  || echo 'clean run, no fatals'

# Watch wire frames flow for one task queue (live)
grep --line-buffered -E '\[(WIRE_INBOUND|WIRE_OUTBOUND)\].*Extra_Transcript_TranscriptRetry' \
  runtime/tests/transcripts/_merged/transcript.log

# Were any PHP warnings or notices raised?
grep '\[ERROR\]' runtime/tests/transcripts/_merged/transcript.log
```

---

## Adding log lines from your own activity code

`Workflow::getLogger()` already routes into the transcript automatically. **Activities** in the SDK don't have a built-in logger, so use the test-only facade:

```php
use Temporal\Tests\Acceptance\App\Logger\ActivityLog;

class PaymentActivity
{
    #[ActivityMethod]
    public function charge(int $invoiceId): bool
    {
        ActivityLog::info('paying invoice', ['invoice_id' => $invoiceId]);
        // ... do work ...
        ActivityLog::warning('payment slow', ['elapsed_ms' => 4200]);
        return true;
    }
}
```

Each call becomes a `[LOG]` line in the worker's transcript file. Outside the acceptance container (e.g. unit tests), the facade is a no-op and prints `[meta] activity_log_unbound` to STDERR.

---

## Environment variables

| Variable | Default | What it does |
|---|---|---|
| `TEMPORAL_WIRE_TRACE` | `"1"` in `tests/Acceptance/.rr.yaml` | When non-`"0"`, the worker host is wrapped with `RecordingHost` so every inbound/outbound frame is captured. Set to `"0"` to disable wire-frame capture but keep logs/exceptions/history. |
| `TEMPORAL_TRANSCRIPT_DIR` | `runtime/tests/transcripts` | Where transcripts are written. Absolute or repo-relative path. |

Both are read by `tests/Acceptance/worker.php` and `tests/Acceptance/App/Logger/LoggerFactory.php`.

---

## Crash-survival guarantees

The transcript is designed so that **a worker crash leaves a usable file behind**.

- Every write is `flock(LOCK_EX) → fwrite → fflush → unflock`. Sub-`PIPE_BUF` lines are atomic per POSIX, so concurrent writers from multiple worker processes cannot corrupt each other.
- `TranscriptWriter` keeps a persistent `fopen('ab')` file descriptor and calls `fflush` on every write — so the line is on disk before `fwrite` returns.
- `FatalHandler` installs three layers:
  - `set_error_handler` — captures `E_WARNING`/`E_NOTICE` as `[ERROR]` and chains to PHP's default (returns `false`).
  - `set_exception_handler` — captures any uncaught throwable as `[FATAL]`, then `exit(1)`.
  - `register_shutdown_function` — if `error_get_last()` reveals an `E_ERROR`/`E_PARSE`/`E_CORE_ERROR`/`E_COMPILE_ERROR`/`E_USER_ERROR`, writes `[FATAL]` and flushes one last time.

### Known limits

These scenarios cannot produce a `[FATAL]` line, but **everything written before** them is still on disk:

- `SIGKILL` / `kill -9` / kernel OOM killer — PHP never runs shutdown handlers.
- Segmentation fault inside a C extension (`ext-grpc`, `ext-protobuf`).
- Stack overflow before the shutdown phase reached.
- Worker killed by RoadRunner's `destroy_timeout`.

For these, you'll see the last `[WIRE_INBOUND]` or `[LOG]` line right before the worker disappeared, with no terminal `[FATAL]` marker. That gap is itself a diagnostic.

---

## Writing your own verification tests

For new acceptance tests that need to assert on what was captured, use `TranscriptReader`:

```php
use Temporal\Tests\Acceptance\App\Logger\LoggerFactory;
use Temporal\Tests\Acceptance\App\Logger\TranscriptReader;
use Temporal\Tests\Acceptance\App\Logger\TranscriptSection;

$reader = new TranscriptReader(LoggerFactory::getTranscriptDirectory());

// Wait until any TEST_END marker shows up (with a hard timeout)
$reader->waitForQuiescence(5000);

// Scope to the lines written between this test's TEST_START and TEST_END
$lines = $reader->linesForTest(static::class, 'testMyScenario');

// Or query directly
$throws = $reader->findBySectionAndAttribute(
    TranscriptSection::EXCEPTION,
    'phase',
    'activity_throw',
);
$wireOutbound = $reader->findBySection(TranscriptSection::WIRE_OUTBOUND);

// Assert ordering
$ok = $reader->hasSequence([
    ['section' => TranscriptSection::WIRE_OUTBOUND, 'attributes' => ['frame_id' => 1]],
    ['section' => TranscriptSection::EXCEPTION, 'attributes' => ['attempt' => 1]],
    ['section' => TranscriptSection::WIRE_OUTBOUND, 'attributes' => ['frame_id' => 2]],
]);
```

Working examples:

- `tests/Acceptance/Extra/Transcript/TranscriptHappyPathTest.php` — minimal round-trip.
- `tests/Acceptance/Extra/Transcript/TranscriptRetryTest.php` — retries with per-attempt assertions.
- `tests/Acceptance/Extra/Transcript/TranscriptWorkflowFailureTest.php` — workflow-side failure.

---

## Section reference (detailed)

| Section | Producer | Key attributes | Payload | Notes |
|---|---|---|---|---|
| `META` | every process | `event` | optional | Lifecycle: `writer_initialized`, `fatal_handler_registered`, `host_recording_started`, `activity_start`, `activity_completed`, `workflow_*_start`, `workflow_*_completed`, `nexus_*_start`, `nexus_*_completed`. |
| `TEST_START` | PHPUnit | `class`, `method` | — | Written before `parent::runTest()`. |
| `TEST_END` | PHPUnit | `class`, `method`, `status`, `duration_ms`, `exception_class?` | — | Written in `finally`. |
| `LOG` | worker | `level`, `message` | original context array | `Workflow::getLogger()` calls + `ActivityLog::*()` facade. |
| `WIRE_INBOUND` | worker | `frame_id`, `bytes` | `headers`, `body` (decoded JSON or base64 raw) | From `RecordingHost::waitBatch`. |
| `WIRE_OUTBOUND` | worker | `frame_id`, `bytes` | `body` (decoded JSON or base64 raw) | From `RecordingHost::send`. Matched with inbound by `frame_id`. |
| `WIRE_ERROR` | worker | `class` | `message`, `trace` | From `RecordingHost::error` — transport-level failure. |
| `EXCEPTION` | worker (interceptors) | `phase`, `name?`, `attempt?`, `activity_id?`, `workflow_id?`, `is_replaying?` | `class`, `message`, `trace`, `previous` | `phase` ∈ `activity_throw`, `workflow_execute`, `workflow_signal`, `workflow_query`, `workflow_update`, `workflow_validate_update`, `nexus_start`, `nexus_cancel`. Each retry attempt produces one line. |
| `FATAL` | either | `class`/`type`, `file`, `line` | `message`, `trace` | From `FatalHandler::register`. |
| `ERROR` | worker | `type`, `file`, `line` | `message` | Non-fatal PHP errors caught by `set_error_handler` — execution continues. |
| `HISTORY` | PHPUnit | `workflow_id`, `run_id`, `event_id`, `event_type`, `event_time` | `attrs` = proto JSON of the `HistoryEvent` | One line per Temporal history event, written in `finally` for every test that injected a `WorkflowStubInterface`. |
| `HISTORY_ERROR` | PHPUnit | `workflow_id`, `class` | `message` | History fetch failed; the test's own failure is preserved. |
| `TRUNCATED` | worker | `reason`, `from`, `to` | — | Soft 50 MB cap reached; writer rotated to `<path>.<N>`. |

---

## Source map (where to read the code)

| File | Role |
|---|---|
| `tests/Acceptance/App/Logger/TranscriptWriter.php` | Append-only, locked, persistent-FD writer. |
| `tests/Acceptance/App/Logger/TranscriptReader.php` | Typed query API: `findBySection`, `findByAttribute`, `hasSequence`, `waitForQuiescence`, `linesForTest`. |
| `tests/Acceptance/App/Logger/TranscriptSection.php` | The enum of section tags. |
| `tests/Acceptance/App/Logger/TranscriptLine.php` | DTO returned by the reader. |
| `tests/Acceptance/App/Logger/TranscriptAdapter.php` | PSR-3 → transcript bridge (used by FanoutLogger). |
| `tests/Acceptance/App/Logger/FanoutLogger.php` | PSR-3 fan-out: writes to existing `FileLogger` AND the transcript. |
| `tests/Acceptance/App/Logger/ActivityLog.php` | Static facade for activity-side `[LOG]` lines. |
| `tests/Acceptance/App/Logger/LoggerFactory.php` | Resolves the transcript directory and creates writers. |
| `tests/Acceptance/App/Logger/ClientLogger.php` | Pre-existing PSR-3 record reader (still used by other tests). |
| `tests/Acceptance/App/Transport/RecordingHost.php` | `HostConnectionInterface` decorator capturing every frame. |
| `tests/Acceptance/App/Runtime/FatalHandler.php` | Three-layer error/exception/shutdown handler. |
| `tests/Acceptance/App/Interceptor/TranscriptActivityInterceptor.php` | Wraps `ActivityInboundInterceptor::handleActivityInbound`. |
| `tests/Acceptance/App/Interceptor/TranscriptWorkflowInterceptor.php` | Wraps all five `WorkflowInboundCallsInterceptor` methods. |
| `tests/Acceptance/App/Interceptor/TranscriptNexusInterceptor.php` | Wraps `NexusOperationInboundCallsInterceptor`. |
| `tests/Acceptance/App/Feature/WorkerFactory.php` | Mounts the three interceptors via `CompositePipelineProvider`. |
| `tests/Acceptance/worker.php` | Worker entry — registers `FatalHandler`, builds host, optionally wraps with `RecordingHost`. |
| `tests/Acceptance/bootstrap.php` | Clears `runtime/tests/transcripts/` at suite start, creates the PHPUnit-side writer, binds it to the container. |
| `tests/Acceptance/App/TestCase.php` | Writes `[TEST_START]/[TEST_END]/[HISTORY]/[HISTORY_ERROR]` and prints the file path to stderr on failure. |
| `tests/Acceptance/.rr.yaml` | Sets `TEMPORAL_WIRE_TRACE=1` and `TEMPORAL_TRANSCRIPT_DIR` for the worker process. |
| `bin/transcript-merge.php` | The `composer test:transcript:last` script. |
| `tests/Unit/Logger/TranscriptWriterTestCase.php` | 11 unit tests for the writer + reader. |
| `tests/Unit/Logger/FatalHandlerTestCase.php` | 3 unit tests proving fatal-error survival. |
| `tests/Acceptance/Extra/Transcript/*.php` | Three live acceptance tests demonstrating happy path / retry / workflow failure capture. |
