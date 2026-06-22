# Plan v2 — End-to-end test transcript: workflow ↔ activity wire stream, exceptions, history, fatal-error survival

**Branch:** none (plan stays on `nexus`; `git.create_branches=false`)
**Created:** 2026-05-13
**Revised:** 2026-05-13 (consolidated four independent reviewer critiques — see "v2 changes" section at the bottom)
**Mode:** full
**Plan file:** `.ai-factory/plans/worker-message-stream-capture.md`

## Settings

- **Testing:** yes — verification is the whole point; unit + acceptance + a fatal-survival smoke test
- **Logging:** verbose — the artifact we are building IS the verbose log. Every new component also emits its own `[META]` lines on init for forensic value
- **Docs:** no (warn-only per project config) — but a single `docs/testing/transcript-capture.md` is mandatory because reviewers flagged the format and env vars must be discoverable (Task #12)
- **Roadmap linkage:** none (no roadmap configured for this repo)

## Goal

After any acceptance test finishes (passed, failed, or worker-crashed), run **one command** (`composer test:transcript:last`) and see the complete chronologically-ordered stream of communication between PHP workers and RoadRunner: every inbound wire frame, every outbound wire response, every PSR-3 log line the SDK emitted, every exception (including ones that were caught and retried), and a full workflow history dump for each workflow the test touched. If the PHP worker dies on a fatal error, the file still contains everything written up to the death plus a final `[FATAL]` marker — no buffered writes lost. The user does not have to know about per-PID file shards; that's an implementation detail behind the merge command.

## What already exists (researched, not invented)

- **PSR-3 file logger:** `tests/Acceptance/App/Logger/FileLogger.php:67` writes `serialize(LogRecord)` lines with `FILE_APPEND | LOCK_EX` to `runtime/tests/logs/<sha1(taskQueue)>.log`. Companion reader: `tests/Acceptance/App/Logger/ClientLogger.php`. We keep these — `ClientLogger` is still consumed by other tests.
- **Worker→RR transport seam:** `src/Worker/Transport/RoadRunner.php:60` (`waitBatch`), `:75` (`send(string $frame, array $headers = [])` — note the optional headers; `WorkerFactory` calls through the single-arg interface so headers are always empty at our seam, but we acknowledge the drift in Task #7). `src/Worker/Transport/HostConnectionInterface.php` is the seam for decoration. Main dispatch loop: `src/WorkerFactory.php:270-289`.
- **Existing error handlers the user remembered:** `src/Internal/Declaration/Dispatcher/Dispatcher.php:109` registers `set_error_handler` per executor invocation; `testing/src/DeprecationCollector.php:14` collects `E_USER_DEPRECATED`; `tests/Acceptance/App/Runtime/RRStarter.php:25` and `TemporalStarter.php` register `shutdown_function` for service teardown. All four stay in place; we install a deeper outer handler that runs **first** (Task #3).
- **History fetch API:** `WorkflowClient::getWorkflowHistory()` (`src/Client/WorkflowClient.php:237`) returns paginated `WorkflowExecutionHistory`. Already iterated by `tests/Acceptance/App/TestCase.php::printWorkflowHistory()` (line 163) — but only on `TemporalException` and only to stdout.
- **Test lifecycle hook:** `tests/Acceptance/App/TestCase.php::runTest()` (line 44) — `[TEST_START]/[TEST_END]` marker site and history flush in `finally`. The existing `terminate('test-end')` at line 152 must run **after** history fetch (Task #6 ordering).
- **Plugin extension point:** `WorkerPluginInterface::configureWorker` lets us attach interceptors outermost via `CompositePipelineProvider` in `src/WorkerFactory.php:191`. This is the correct seam for transcript interceptors — NOT the per-feature `tests/Acceptance/App/Feature/WorkerFactory.php`, which would clobber feature-supplied pipelines (architecture reviewer).
- **Replay log filter:** `src/Internal/Workflow/Logger.php:80` skips replay logs unless `enableLoggingInReplay=true`. We flip it on for tests and tag each line with `is_replaying=true|false` so retry assertions can dedupe (test-strategy reviewer).
- **`Activity` has no `getLogger()`** (spec-coverage reviewer confirmed by reading `src/Activity/`). Workflows can log via `Workflow::getLogger()`; activities can't. Task #16 adds a test-only `ActivityLog` facade to close this hole — without it the transcript would silently miss activity user-code logs.

## Gaps the plan closes

1. No wire-level tap — frames flow through `RoadRunner::waitBatch`/`send` without record.
2. PSR-3 logs persist via `FileLogger` only; on failure only stdout shows them; no unified human-readable transcript.
3. History is dumped to stdout only on `TemporalException` — not for passes, not for unexpected `\Error`s, not as a parseable file.
4. `tests/Acceptance/worker.php:115` catch calls `td($e)` — an undefined function, silent NOOP. Any fatal in the worker dies invisibly.
5. No `set_exception_handler` / `register_shutdown_function` for fatal error capture anywhere in `src/` or `tests/Acceptance/App/`.
6. No mechanism for activity user-code to log into the unified stream.
7. No discoverability — even if we wrote the file, the user has to guess where it is. Task #15 fixes this with `composer test:transcript:last`.

## Design at a glance

```
┌───────────────────────────────────────────────────────────────────────┐
│  worker.php (PHP worker process, spawned by RR)                       │
│                                                                       │
│   1st statement:                                                      │
│   FatalHandler::register($bootstrapTranscript)                        │
│     ├─ set_error_handler        → [ERROR]   (returns false, chains)   │
│     ├─ set_exception_handler    → [FATAL]                             │
│     └─ register_shutdown_function (E_ERROR/E_PARSE/...) → [FATAL]     │
│         (registered FIRST so it flushes BEFORE RRStarter/Temporal-    │
│          Starter shutdown functions tear down)                        │
│                                                                       │
│   After RuntimeBuilder::createState() resolves task queue:            │
│     $transcript = LoggerFactory::createTranscriptWriter($tq)          │
│     // persistent fd; per-write: flock+fwrite+fflush+unflock          │
│     FatalHandler::rebindWriter($transcript)                           │
│                                                                       │
│   FanoutLogger(FileLogger, TranscriptAdapter)                         │
│     │     ↑ wraps existing FileLogger; SDK logs land in both          │
│     │       TranscriptAdapter tags replay lines with is_replaying=…   │
│     │                                                                 │
│   TranscriptCapturePlugin (WorkerPluginInterface)                     │
│     ├─ TranscriptActivityInterceptor      → [EXCEPTION activity_*]    │
│     ├─ TranscriptWorkflowInterceptor (5 methods) → [EXCEPTION wf_*]   │
│     └─ TranscriptNexusInterceptor         → [EXCEPTION nexus_*]       │
│                                                                       │
│   $host = TEMPORAL_WIRE_TRACE                                         │
│       ? new RecordingHost(RoadRunner::create(), $transcript)          │
│       : RoadRunner::create()                                          │
│   $workerFactory->run($host)                                          │
│                                                                       │
│   Activity user code uses ActivityLog::info(...)  → [LOG] via fanout  │
└──────────────────────────────┬────────────────────────────────────────┘
                               │ writes via persistent FD:
                               │   flock LOCK_EX → fwrite → fflush → LOCK_UN
                               ▼
   runtime/tests/transcripts/<taskQueue-safe>__pid<pid>__<startMs>.log
                               ▲
                               │
┌──────────────────────────────┴────────────────────────────────────────┐
│  TestCase::runTest()  (PHPUnit process — different process, same      │
│                        filename when matched)                         │
│                                                                       │
│   [TEST_START] via TranscriptWriter (PHPUnit's own FD, own file)      │
│   ... parent::runTest() ...                                           │
│   finally:                                                            │
│     for each WorkflowStubInterface arg:                               │
│        getWorkflowHistory($exec,                                      │
│          filter=$testPassed ? CLOSE_EVENT : ALL_EVENT)                │
│        → [HISTORY] event_id=... type=... attrs_json=...               │
│        (errors trapped → [HISTORY_ERROR])                             │
│     terminate('test-end')   // AFTER history, not before              │
│     [TEST_END] status=passed|failed|skipped exception_class=...       │
│                                                                       │
│   On failure: stderr emits                                            │
│     [transcript] /abs/path/merged.log                                 │
│     [transcript] run `composer test:transcript:last` to view          │
└───────────────────────────────────────────────────────────────────────┘
                               │
                               ▼
          composer test:transcript:last
               (Task #15 merger script)
                               │
                               ▼
        runtime/tests/transcripts/_merged/<taskQueue-safe>.log
                               │
                               ▼
                ┌──────────────┴──────────────┐
                │  TranscriptReader (Task #14) │
                │  used by verification tests  │
                └──────────────────────────────┘
```

## File format

Line schema: `<ISO8601-ts> <pid> <seq> [<SECTION>] key=value ... payload=<one-line-json>`

Sections: `TEST_START`, `TEST_END`, `LOG`, `WIRE_INBOUND`, `WIRE_OUTBOUND`, `WIRE_ERROR`, `EXCEPTION`, `FATAL`, `ERROR`, `HISTORY`, `HISTORY_ERROR`, `META`, `TRUNCATED`.

CLAUDE.md disallows abbreviations — renamed `WIRE_RX/TX/ERR` from v1 to `WIRE_INBOUND/OUTBOUND/ERROR`.

Filename schema: `<taskQueue-safe>__pid<pid>__<workerStartEpochMs>.log` — readable slug instead of pure sha1; epoch ms distinguishes worker-restart orphans (operational reviewer).

Full reference in `docs/testing/transcript-capture.md` (Task #12).

## Tasks

### Phase 1 — Foundation

**Task 1 — `TranscriptWriter`** (no deps) — [x] DONE
Persistent `fopen('ab')` FD; per-write `flock LOCK_EX` → `fwrite` → `fflush` → `unflock` (operational reviewer correction — per-line `file_put_contents` is 10× slower and the `fflush` is the durability guarantee). Re-entrancy contract: catches every internal `\Throwable`, emits to STDERR only, never throws upward. Rotation policy: soft 50MB cap with `[TRUNCATED]` marker. Section enum renamed per CLAUDE.md (no abbreviations).

**Task 8 — Container-register `TranscriptWriter`** (blocked by #1)
Per-process singleton. PHP worker and PHPUnit are separate processes — coordination is via `(filename, LOCK_EX)`, not via container. Filename includes `worker_start_epoch_ms` so restarted-worker files don't collide.

### Phase 2 — Capture sources

**Task 2 — `RecordingHost` wire-tap decorator** (blocked by #1)
Implements `HostConnectionInterface`, wraps `RoadRunner` host. On `waitBatch`/`send`/`error` writes `[WIRE_INBOUND] / [WIRE_OUTBOUND] / [WIRE_ERROR]` with raw frame bytes, length, monotonic frame_id. Wire-tap never throws upward.

**Task 3 — `FatalHandler`** (blocked by #1)
The linchpin of fatal-error survival. **Must be the very first statement of `worker.php`** (spec reviewer) so its shutdown function runs before `RRStarter`/`TemporalStarter` teardown. Three layers: `set_error_handler` (chains beneath Dispatcher's per-function handler), `set_exception_handler`, `register_shutdown_function`. Replaces broken `td($e)` with `$writer->writeFatal($e); fwrite(STDERR, (string)$e); exit(1);` (architecture reviewer: cleaner than rethrow). Re-entry guard prevents recursion on second fatal.

**Task 4 — `FanoutLogger` + `TranscriptAdapter` PSR-3 bridge** (blocked by #1)
Fan-out PSR-3 → both `FileLogger` (preserved for `ClientLogger` consumers) and the transcript. Replay logs tagged `is_replaying=true|false` via `Workflow::isReplaying()`. `WorkerOptions::withEnableLoggingInReplay(true)` so replay logs aren't pre-filtered.

**Task 5 — Activity & Workflow exception-capture interceptors** (blocked by #1, #8, #17)
Mounted via `TranscriptCapturePlugin` (`WorkerPluginInterface`) — NOT inside the per-feature `WorkerFactory` (architecture reviewer: would clobber feature-supplied pipelines). Activity interceptor wraps `handleActivityInbound`; workflow interceptor wraps all five methods (`execute`, `handleSignal`, `handleQuery`, `handleUpdate`, `validateUpdate` — test-strategy reviewer). Lines tagged `is_replaying=...` for retry-test dedup.

**Task 17 — `TranscriptNexusInterceptor`** (blocked by #1, #8)
Parity with activity/workflow on the `nexus` branch. Implements `NexusOperationInboundCallsInterceptor`, mounted via the same `TranscriptCapturePlugin`. Lines carry `service=...`, `operation=...`, `operation_token=...`. Without this, Nexus exceptions are silently invisible (test-strategy reviewer flagged as MAJOR for the current branch's scope).

**Task 6 — Persist workflow history dump on every test** (blocked by #1, #8)
History fetch runs in `finally`, **BEFORE** `terminate('test-end')` (test-strategy reviewer: previously the existing terminate killed long-running workflows pre-fetch). Filter mode: `CLOSE_EVENT` for passing tests, `ALL_EVENT` for failing — operational reviewer flagged unconditional full-history-on-pass as the dominant fast-suite latency. Override via `TEMPORAL_HISTORY_DUMP_FULL=1`.

**Task 16 — `ActivityLog` facade** (blocked by #4, #8)
Static facade so activity user code can write into the unified stream. Required because `Activity` has no `getLogger()` (spec reviewer). Acceptance-only — production scope intentionally untouched. Documented in Task #12.

### Phase 3 — Reader + ergonomics

**Task 14 — `TranscriptReader`** (blocked by #1)
Mirrors `ClientLogger`'s API (`getRecords`, `findByMessage`, `findByLevel`) — `findBySection`, `findByAttribute`, `lastTestBoundaries`, `waitForQuiescence`, `hasSequence`. Used by Task #9 verification tests so assertions aren't brittle grep. Malformed lines raise `MalformedTranscriptException` with position.

**Task 15 — Merged transcript view + `composer test:transcript:last`** (blocked by #14)
The "open one file" ergonomic fix that all four reviewers asked for. `bin/transcript-merge.php` + composer script + `MergedTranscriptView` class. Merged output at `runtime/tests/transcripts/_merged/<taskQueue-safe>.log`. PHPUnit `TestCase` prints `[transcript] <abs-path>` to stderr on test failure (discoverability).

### Phase 4 — Wiring

**Task 7 — Plug `RecordingHost` into the worker entry under `TEMPORAL_WIRE_TRACE`** (blocked by #2, #3, #8, #15)
Build host explicitly in `worker.php`, optionally wrap, pass to `run($host)`. Wire-trace env on by default in `tests/Acceptance/.rr.yaml`; transcript dir env documented. `tests/Acceptance/bootstrap.php`: **truncate transcripts at suite START** (not end — clearing at end defeats the artifact). Print one startup line with capture state + merge command. `.gitignore` adds `runtime/tests/transcripts/`.

### Phase 5 — Verification

**Task 9 — Acceptance: round-trip / exception / retry / signal / Nexus capture** (blocked by #4, #5, #6, #7, #14, #16, #17)
Five test methods using `TranscriptReader` (NOT raw glob+strpos): happy path, 3-attempt retry with explicit ordering assertion via `hasSequence`, workflow failure, signal+update paths, Nexus operation retry. Each test calls `waitForQuiescence(5000)` before asserting (race fix). Wire-frame round-trip is proto-decoded to prove capture isn't garbage.

**Task 10 — Fatal-survival smoke test** (blocked by #3)
Three out-of-band fixtures spawned via `proc_open`: `E_USER_ERROR`, uncaught `\Error` from a destructor, Dispatcher-chain interaction. Suite: Acceptance-Fast (no RR/Temporal needed). Documented limits (SIGKILL, OOM, segfault, stack overflow) appear in Task #12's docs, not in test assertions — they're known irreducible cases.

**Task 11 — TranscriptWriter + TranscriptReader unit tests** (blocked by #1, #14)
Pure unit, no RR. Covers persistent-FD durability via `proc_open` child + fatal trigger (test-strategy reviewer's BLOCKER fix — proves `fflush` reaches disk before PHP teardown). `pcntl_fork` test gated by `function_exists` with a `proc_open` fallback for portability. Rotation behavior verified. Reader's `hasSequence`, `waitForQuiescence`, malformed-line behavior covered.

**Task 12 — Documentation** (blocked by #15, #16)
`docs/testing/transcript-capture.md`. Single page. Where the file is, how to find it (`composer test:transcript:last`), env vars, section reference, line schema, cleanup policy, replay-tag semantics, **activity-side logging via `ActivityLog`** (otherwise this is the silent hole), known limits (SIGKILL etc.), grep recipes, cross-links to `ClientLogger` + `TranscriptReader`.

**Task 13 — Quality gate** (blocked by #9, #10, #11, #12)
`cs:diff`, `psalm`, `test:unit`, `test:accept-fast`, `test:accept-slow`. Manually run `composer test:transcript:last` and visually verify layout. Orphan-file detection check. Discoverability check (stderr `[transcript]` line on failure).

## Dependency graph

```
1 ────┬──→ 2 ───┐
      ├──→ 3 ───┤
      ├──→ 4 ───┤───────────┐
      ├──→ 8 ─┬─→ 5 ◄── 17 ──┤
      │        │             │
      │        └─→ 6 ────────┤
      │                      │
      ├──→ 14 ──→ 15 ────────┤
      │                      │
      ├──→ 17                │
      │                      │
      └──→ 11                │
                             │
   2,3,8,15 ──→ 7 ───────────┤
                             │
   4,16 ───→ 16              │
                             │
   4,5,6,7,14,16,17 ──→ 9 ───┤
   3 ──────────────────→ 10 ─┤
   1,14 ───────────────→ 11 ─┤
   15,16 ──────────────→ 12 ─┤
                             │
   9,10,11,12 ──────────→ 13 ◄
```

## Commit plan

17 tasks → 7 commits, conventional-commit style:

1. **`feat(testing): introduce persistent-FD transcript writer with container wiring`** — Tasks #1, #8.
2. **`feat(testing): build transcript reader and merged-view ergonomics`** — Tasks #14, #15.
3. **`feat(testing): record wire frames, fatal errors, and SDK logs to transcript`** — Tasks #2, #3, #4.
4. **`feat(testing): tap activity/workflow/Nexus exceptions and persist workflow history per test`** — Tasks #5, #6, #16, #17.
5. **`feat(testing): enable transcript capture in acceptance worker by default`** — Task #7. (Single-task commit — user-visible activation point; bisect-friendly.)
6. **`test(testing): cover transcript capture end-to-end including fatal survival`** — Tasks #9, #10, #11.
7. **`docs(testing): document the transcript file format`** — Task #12.

Task #13 produces no commit unless cleanup diffs surface.

## Risk register

- **Replay log noise.** `withEnableLoggingInReplay(true)` plus `is_replaying=true|false` tag — users grep `replay=false` for the common path. Decision: tag-and-keep, not filter-and-lose.
- **Cross-process file coordination.** PHPUnit and worker PIDs write to different files; `LOCK_EX` serializes writes when they happen to share a filename. The merged view (Task #15) is the user-facing artifact.
- **Performance.** Persistent FD + `fflush` per write + CLOSE_EVENT-only history for passing tests keeps overhead modest. Task #13 measures fast-suite regression; if >5% the fallback is `TEMPORAL_WIRE_TRACE=0` for the fast-suite RR config split.
- **Worker restart orphans.** Filename includes `worker_start_epoch_ms`; the merger reports orphans on stderr but doesn't include them in the merged view.
- **Fatal handler limits.** SIGKILL, OOM kill, segfault in C extension, stack overflow before shutdown phase do NOT produce `[FATAL]`. LOCK_EX still protects already-flushed frames. Documented in Task #12.
- **`td($e)` rethrow change.** Replaced with explicit `exit(1)` after `writeFatal`. If any acceptance test relied on the silent swallow (unlikely — `td()` is undefined → triggers PHP error), Task #13's gate surfaces it.
- **Activity-side log facade limited to acceptance.** Production activity code remains free of test-only conventions. Hooks for production-side logging are out of scope.
- **Plugin-vs-per-feature interceptor seam.** Tested by feature-specific tests that supply their own `pipelineProvider` — confirm in Task #13 that those tests still pass with the plugin in place.

## v2 changes (consolidated from four independent reviewers)

**Architecture review** (`general-purpose` agent): moved interceptor registration from per-feature `WorkerFactory` to `WorkerPluginInterface`; clarified that worker container and PHPUnit container are independent; called out `RoadRunner::send` optional-headers signature drift (no production change needed — interface-typed callers always pass empty).

**Test-strategy review** (`general-purpose` agent): added `TranscriptReader` (Task #14) so assertions aren't brittle grep; mandated `waitForQuiescence` before assertions; added Nexus interceptor (Task #17) and signal/update test coverage; ordered history-fetch BEFORE `terminate()`; tagged replay-emitted lines so retry assertions dedupe; required all five `WorkflowInboundCallsInterceptor` methods to be wrapped; added uncaught-`\Error` fixture + Dispatcher-chain fixture to Task #10; added shutdown-time durability test to Task #11.

**Operational review** (`general-purpose` agent): replaced per-line `file_put_contents` with persistent FD + `flock`+`fwrite`+`fflush` (10× faster, same durability); added `worker_start_epoch_ms` to filenames for orphan distinction; switched history fetch default to `CLOSE_EVENT` for passing tests; documented SIGKILL/segfault/OOM limits; added soft 50MB rotation cap.

**Spec-coverage review** (`general-purpose` agent): added merge command + discoverability stderr line (Task #15); added `ActivityLog` facade (Task #16) so activity-side user code participates in the stream; mandated FatalHandler registration as the first statement of `worker.php`; renamed sections per CLAUDE.md (no abbreviations); switched filenames from pure sha1 to readable slug; mandated suite-start (not end) truncation; added retry-ordering contract via `hasSequence`.

Three findings were corroborated by 3+ reviewers (one-file ergonomics; activity logger gap; FatalHandler ordering vs existing shutdown functions) — strong signal those were real. One claim was investigated and downgraded: the `HostConnectionInterface::send` "BLOCKER" turned out to be MINOR (interface-typed callers pass empty headers; decorator is fine).

## Next steps

```
/aif-implement
```

Phase 1 (Tasks #1, #8) unblocks everything. Within Phase 2 the four capture sources (#2, #3, #4, #17) are independent and parallelizable. Phase 5's verification (#9, #10, #11) requires all capture + reader work to land first.
