# Debug Plan: locate the flake in `MultipleCallersTest` (and the 1/170 acceptance-fast flake)

Created: 2026-05-13
Branch: `nexus` (no new branch — `git.create_branches: false`)
Type: **diagnostic runbook, NOT a fix plan**

## Settings

- Testing: yes (this *is* a test-stability investigation)
- Logging: temporary verbose for RR + a one-off PHP shutdown handler in `tests/Acceptance/worker.php` (both reverted after diagnosis)
- Docs: no

## Goal

Find the actual failure mode of `MultipleCallersTest` (and the broader `Acceptance-Fast` 1/170 flake observed earlier) **without** any startup-poll, `usleep`, or timeout bump. Specifically — test the hypothesis that a PHP worker crashes mid-task and the symptom looks like "stuck operation" only because the surviving pool is too small to mask it.

## Hypothesis under test

> RoadRunner's default workflow worker pool is 1. If that single PHP process panics, segfaults, or `exit()`s while holding a workflow task, the task stays unacked until RR respawns a worker — and any test that races against that respawn fails with `SCHEDULE_TO_*` / wall-clock timeout.

If true:
- bumping `pool.num_workers` will change the symptom (some tests survive a single crash → fewer red runs)
- crash signatures will appear in RR logs and a shutdown-handler log

If false:
- bumping workers will not change failure rate → race is somewhere else (test code, server, Nexus dispatch)

Either outcome is informative.

## Out of scope (hard "no"s)

- ❌ `waitForTaskQueuePollers` or any `temporal task-queue describe` polling in bootstrap. Already rolled back. **Will not be reintroduced.**
- ❌ `usleep` / `sleep` anywhere in test or framework code
- ❌ bumping `withScheduleToCloseTimeout` / `withWorkflowExecutionTimeout` to "give it more time"
- ❌ touching `src/Nexus/WorkflowRunOperation.php` `UseExisting` policy
- ❌ touching the rolled-back files in `testing/src/Environment.php` and `tests/Acceptance/App/Runtime/RRStarter.php`
- ❌ committing any of the temporary diagnostic changes from this plan

## Affected (temporary, must be reverted)

```
tests/Acceptance/.rr.yaml                       (worker count + log level)
tests/Acceptance/worker.php                     (shutdown handler + uncaught exception trap)
logs/                                           (new — add to .gitignore for the duration)
histories/                                      (new — add to .gitignore for the duration)
```

No production source files (`src/`) are touched at any point in this plan.

---

## Phase 1 — Run `MultipleCallersTest` 10 times, look at the output

No conditional escalation, no "if green then more". 10 runs. Read all 10 outputs.

```bash
mkdir -p logs histories

for run in $(seq 1 10); do
    printf '\n=== mc run %d ===\n' "$run"
    tests/runner.php vendor/bin/phpunit \
        --testsuite=Acceptance-Fast \
        --filter='MultipleCallersTest' \
        > "logs/mc-${run}.stdout.log" \
        2> "logs/mc-${run}.stderr.log"
    printf 'exit=%d\n' "$?"
done
```

Note: no `break` on failure. Every run produces a pair of log files; all 10 are inspected.

After the loop:

```bash
# pass/fail summary
grep -E 'OK|FAILURES|ERRORS' logs/mc-*.stdout.log

# any worker death across all 10 runs
grep -nE 'panic|fatal|signal|killed|stopped|exit' logs/mc-*.stderr.log
```

**Outputs to read:**
- `logs/mc-1..10.stdout.log` — PHPUnit verdict per run
- `logs/mc-1..10.stderr.log` — RR stderr per run (worker lifecycle events)
- Workflow IDs of any failed run for Phase 3

## Phase 2 — Make crashes survivable AND visible

Edit `tests/Acceptance/.rr.yaml` to:

```yaml
version: "3"
rpc:
    listen: tcp://127.0.0.1:6001

server:
    command: "php worker.php"

pool:
    num_workers: 4
    max_jobs: 0
    supervisor:
        watch_tick: 1s
        ttl: 0s
        idle_ttl: 0s
        max_worker_memory: 0
        exec_ttl: 0s

temporal:
    address: ${TEMPORAL_ADDRESS:-127.0.0.1:7233}
    namespace: ${TEMPORAL_NAMESPACE:-default}
    activities:
        num_workers: 4

kv:
    harness:
        driver: memory
        config: { }

logs:
    level: debug
    encoding: console
    output: stderr
    mode: production
```

What changes vs the committed config:
- `pool.num_workers: 4` — the workflow worker pool is now 4. Previously implicit 1.
- `activities.num_workers: 4` — was 2.
- `logs.level: debug` — was `info`. Surfaces `worker created`, `worker stopped`, `process exited`, `panic`, signal events.
- Logs go to stderr → captured by the `run-N.stderr.log` files from Phase 1.

**What to grep for in `logs/mc-N.stderr.log`:**

```bash
grep -E 'panic|fatal|signal|killed|exit|stopped|created|allocated' logs/mc-N.stderr.log
```

Expected healthy pattern: each worker line is `worker created pid=...` once, then steady-state polls. **Unhealthy pattern**: repeated `worker stopped` + `worker created` cycles, especially clustered around the failure timestamp.

If `num_workers: 4` makes the test green:
- it does **not** mean the bug is fixed — it means there's a crash that 4 workers can hide
- next step: find which PHP code crashes the worker (Phase 4)

If `num_workers: 4` still fails:
- the bug is **not** a worker crash → investigate Phase 3 (history forensics) more carefully and look at server-side / dispatch path

## Phase 3 — Workflow-history forensics

For each failure, dump the workflow histories for everything that ran in the last few seconds:

```bash
# list recent workflows in the namespace
temporal workflow list \
    --address "${TEMPORAL_ADDRESS:-127.0.0.1:7233}" \
    --namespace "${TEMPORAL_NAMESPACE:-default}" \
    --output json \
    --limit 50 \
  > histories/recent.json

# for each workflow ID, dump full history
jq -r '.[].execution.workflowId' histories/recent.json \
  | while read -r wf; do
        temporal workflow show \
            --workflow-id "$wf" \
            --output json \
          > "histories/$(echo "$wf" | tr '/:' '__').json"
    done
```

For each saved history, read top-to-bottom looking for:

| Event | What it means |
|-------|---------------|
| `WorkflowTaskTimedOut` + `cause: ScheduleToStart` | No poller picked the task up. → poll path issue (network / RR / worker pool). |
| `WorkflowTaskTimedOut` + `cause: StartToClose` | Worker picked it up, then died/hung. → PHP-side crash or deadlock. |
| `WorkflowTaskFailed` + `cause: NonDeterministicError` | History replay diverged. → workflow code is non-deterministic. |
| `WorkflowTaskFailed` + `cause: WorkflowWorkerUnhandledFailure` | PHP threw past the SDK's catch. → look at `tests/Acceptance/worker.php` shutdown log (Phase 4). |
| `NexusOperationTimedOut` | Caller's nexus operation hit `ScheduleToCloseTimeout`. → check if handler workflow ever attached. |
| Gap >5s between `WorkflowTaskScheduled` and `*Started` | Poller stall. → cross-ref Phase 4. |

This is the **objective failure mode**, not a hypothesis. Whatever the event says — that's the bug.

## Phase 4 — PHP-side crash log

`tests/Acceptance/worker.php` currently runs the `WorkerFactory` happy path. RR will report a worker death as a generic "process exited" without the original PHP error. Capture it directly.

Add at the **very top** of `tests/Acceptance/worker.php` (right after the autoloader require):

```php
\register_shutdown_function(static function (): void {
    $error = \error_get_last();
    if ($error === null) {
        return;
    }
    if (!\in_array($error['type'], [\E_ERROR, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR, \E_RECOVERABLE_ERROR], true)) {
        return;
    }
    \file_put_contents(
        \dirname(__DIR__, 2) . '/logs/php-worker-crash.log',
        \sprintf("%s pid=%d %s\n", \date('c'), \getmypid(), \json_encode($error)),
        \FILE_APPEND,
    );
});

\set_exception_handler(static function (\Throwable $e): void {
    \file_put_contents(
        \dirname(__DIR__, 2) . '/logs/php-worker-crash.log',
        \sprintf(
            "%s pid=%d uncaught %s: %s\n%s\n",
            \date('c'),
            \getmypid(),
            $e::class,
            $e->getMessage(),
            $e->getTraceAsString(),
        ),
        \FILE_APPEND,
    );
});
```

Then `tail -F logs/php-worker-crash.log` in another terminal during Phase 1.

**Important:** this is a diagnostic-only change to `worker.php`. It is reverted in Phase 6 before any commit.

## Phase 5 — Cross-correlate by timestamp

When a failed run is identified:

1. PHPUnit failure has a timestamp (or you note wall-clock from the loop)
2. Workflow history has `eventTime` on every event
3. `logs/mc-N.stderr.log` has RR's debug log lines with timestamps
4. `logs/php-worker-crash.log` has wall-clock entries

Pick a 5-second window around the failure timestamp. Read all four sources within that window in order:

```
HH:MM:SS.000  Temporal  WorkflowTaskScheduled  wf=Caller-X
HH:MM:SS.050  RR        worker stopped pid=12345
HH:MM:SS.052  PHP       pid=12345 uncaught FooException: ...
HH:MM:SS.300  RR        worker created pid=12346
HH:MM:SS.500  Temporal  WorkflowTaskTimedOut  cause=StartToClose
```

This sequence is the actual diagnosis. Any single source on its own is not enough.

## Phase 6 — Revert ALL diagnostic changes

Mandatory. Do not skip. Do not "leave the verbose logging in, it's useful".

```bash
git checkout -- tests/Acceptance/.rr.yaml tests/Acceptance/worker.php
rm -rf logs/ histories/
```

The diagnosis goes into a **new** plan file. The fix (if any) is a separate decision, separate plan, separate commit.

---

## Decision rules after diagnosis

| Diagnosis | Next plan |
|-----------|-----------|
| Worker crash with a specific PHP exception | Fix the throwing code path in `src/...`. New plan. |
| `NonDeterministicError` in the caller workflow | Inspect the workflow code for unguarded `time()` / `rand()` / external IO. New plan. |
| `ScheduleToStart` timeout with no worker crash | The race is at the SDK-poll or RR-plugin level — **not** "warm up workers in bootstrap". File an issue for the plugin / SDK with the captured history. |
| Test-level race (e.g. `getResult()` returns before completion event) | Fix the test assertion strategy. New plan. |
| Cannot reproduce after 50 runs | The bug is environmental (CI vs local, kernel scheduling). Note this and move on. |

## What this plan will NOT produce

- ❌ A "small helper" in `Environment` that does `sleep`/poll under any name
- ❌ A `withTimeout(longer)` patch on any test
- ❌ A per-test `try-catch-and-retry` loop in the test body
- ❌ A change to `WorkflowIdConflictPolicy::UseExisting` hardcoding without an explicit, separate decision
