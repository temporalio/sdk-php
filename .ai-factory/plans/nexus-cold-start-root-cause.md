# Implementation Plan: bundled-server readiness — pollers must be alive before PHPUnit dispatches

Created: 2026-05-13
Branch: nexus (no new branch — `git.create_branches: false`)

## Settings

- Testing: yes — repeated bundled-server runs of `Acceptance-Fast` must be 170/170
- Logging: standard — no new DEBUG sprinkled across framework
- Docs: no

## Why (observed problem)

After my Nexus-side fixes landed (plugin `nexusErrorFromFailure` swap + `MultipleCallersTest` rewrite + `SyncFailureTest` walker revert + `bootstrap.php` SA-list), repeated bundled runs of `tests/runner.php vendor/bin/phpunit --testsuite=Acceptance-Fast` are flaky: 1 random test out of 170 fails per run (Run 1 = `Retry policy`, Run 2 = `Caller catches nexus operation failure with application failure cause`, Run 3 = `Worker boots with nexus service`). The same suite against a separately-started `temporal server start-dev` is 170/170 deterministic.

Root cause — confirmed by tracing `Environment::startRoadRunner` and the failed-workflow histories:

1. `Environment::startRoadRunner` declares ready on the literal `"RoadRunner server started"` line in RR's stdout.
2. PHP worker processes then autoload, build `WorkerFactory`, and only after that start long-poll RPCs (`PollWorkflowTaskQueue`, `PollActivityTaskQueue`, Nexus poll).
3. Tests start in that gap. First request on a queue with no live poller waits in matching service until `schedule_to_close` and times out.

The user has explicitly refused two prior approaches:

- bumping individual test timeouts (`withScheduleToCloseTimeout` / `withWorkflowExecutionTimeout`) — masks the race, not fixes it.
- per-test `waitForWorkerPollers` invoked from `TestCase::runTest` — duplicate work per test, framework-level concern leaks into test code, the shelved patch carries exactly this anti-pattern.

The user's requested direction: shell out to `temporal` CLI for the readiness probe (same shape as the existing `temporal operator cluster health` loop in `Environment::startTemporalServer`), put it in framework code that already calls `temporal` CLI, run it **once** at framework startup.

## Out-of-scope

- Modifying `roadrunner-temporal` plugin or the rebuilt `rr` binary (the cold-start gap is on the PHP-worker boot path, not the plugin).
- Modifying any production source under `sdk-php/src/`.
- Touching `WorkflowRunOperation::start` (the `catch AlreadyStartedException` experiment was wrong and was already reverted).
- Adding a sleep anywhere — bounded poll-until-ready only.
- Hydrating `State::$features` eagerly in bootstrap — that was tried and silently dropped 6 tests from PHPUnit discovery (170 → 164 with 4 warnings). Hydration must stay lazy.

## Affected files

```
sdk-php/testing/src/Environment.php                            (add waitForTaskQueuePollers — already half-done; verify the CLI JSON probe is reliable)
sdk-php/tests/Acceptance/App/Runtime/RRStarter.php             (call waitForTaskQueuePollers from start() in a way that doesn't require State hydration)
```

The current half-applied state (kept):
- `Environment::waitForTaskQueuePollers(string $taskQueue, string $taskQueueType, int $timeout): bool` — shells out to `temporal task-queue describe --task-queue <X> --task-queue-type <Y> --address <addr> --output json`, polls every 50ms, returns `true` when stdout contains `"identity"`.
- `RRStarter::start()` calls it after `$this->environment->startRoadRunner(...)` — currently iterates `$this->runtime->features`, but at bootstrap time that list is empty (State hydration is lazy via `TestCase::setUp`), so the loop is a no-op.

The current half-applied state to revert / replace:
- The `$this->runtime->countFeatures() === 0 and RuntimeBuilder::hydrateClasses(...)` line I just added inside `RRStarter::start()` — user interrupted it before the test run that would have validated the approach. Need to decide before retrying whether forcing hydration at this point has any of the side effects we saw at bootstrap level (PHPUnit discovery loss).

## Subagent investigation needed before writing more code

Spawn parallel `general-purpose` agents to settle the three open questions. Do not start the implementation tasks until all three reports are in.

### Subagent 1 — Hydration side-effect audit

> In `sdk-php/tests/Acceptance/`, what does `RuntimeBuilder::hydrateClasses(State)` do that interferes with PHPUnit's own test discovery? Empirically, calling it eagerly in `bootstrap.php` drops PHPUnit's test count from 170 to 164 with 4 warnings. Calling it lazily from `TestCase::setUp()` does not. Identify the specific mechanism — `ClassLocator::loadTestCases` includes test files via require, `get_declared_classes` ordering, attribute-reading on test classes before phpunit attaches, double-load between bootstrap include and phpunit's TestSuite::run, etc. Output: a one-paragraph diagnosis pointing at the exact file + behavior that triggers the discrepancy, and whether calling hydrate from `RRStarter::start()` (after RR is up, before PHPUnit starts dispatching tests) has the same side effect or not. Report in ≤ 250 words.

### Subagent 2 — temporal CLI JSON shape verification

> Run `temporal task-queue describe --task-queue <name> --task-queue-type workflow --address 127.0.0.1:7233 --output json` against a Temporal CLI v1.6.2 dev server (server v1.30.2) in two states: (a) immediately after `temporal server start-dev` returns, no workers attached; (b) after a PHP worker is polling the queue. Compare both stdout payloads. Verify whether `"identity"` is a reliable substring marker for "at least one poller exists" — i.e. is it absent in (a) and present in (b)? If not, propose a stricter marker (e.g. parsing `pollers` array via `json_decode`, or matching on `"lastAccessTime"` / `"workerVersionCapabilities"` / etc). Output: the exact JSON shapes for both cases plus the concrete check we should use in `Environment::waitForTaskQueuePollers`. Report in ≤ 250 words.

### Subagent 3 — what task queues exist at RR-start without State hydration

> In `sdk-php/tests/Acceptance/`, when `RRStarter::start()` runs, how can we enumerate the task queues that the PHP worker process will register, without hydrating `State::$features` (which has PHPUnit-discovery side effects per subagent 1)? Options to evaluate:
> (a) Each test class with `#[Worker(...)]` declares its task queue as the class's PHP namespace — could discover by directory scan + Reflection on `#[Worker]` attribute only, skipping the full workflow/activity/nexus reflection that hydrateClasses does.
> (b) Read `tests/Acceptance/worker.php` to see which task queues it registers at PHP-worker boot — maybe it already does discovery we can mirror.
> (c) Skip per-queue checks and just probe a single canonical "smoke" queue we add to the test bootstrap (drop-in worker that exists only to serve as readiness probe).
> Output: which option is least invasive, with file/line references for what we'd need to read/add. Report in ≤ 300 words.

## Tasks (do NOT start until all three subagent reports above are in)

- [ ] **Task 1 — Apply subagent 2's verdict to `Environment::waitForTaskQueuePollers`**
      File: `sdk-php/testing/src/Environment.php`. Replace the current `\str_contains($check->getOutput(), '"identity"')` check with whatever subagent 2 confirms is the reliable marker (likely `json_decode` + check for non-empty `pollers` array under the right key). Keep the same shape as the surrounding `temporal operator cluster health` loop.

- [ ] **Task 2 — Wire `waitForTaskQueuePollers` into `RRStarter::start()` per subagent 1 + 3**
      File: `sdk-php/tests/Acceptance/App/Runtime/RRStarter.php`.
      - If subagent 1 confirms hydration from this call site is safe → keep the `countFeatures() === 0 and hydrateClasses(...)` line and iterate features.
      - If hydration here is also unsafe → switch to subagent 3's chosen alternative (e.g. lightweight `#[Worker]`-only attribute scan).
      - In either case the call site is `RRStarter::start()` after `$this->environment->startRoadRunner(...)`. Wait per-queue per-type (workflow / activity / nexus) only when the feature actually has those declared, so we don't probe queue types that no worker will register.

- [ ] **Task 3 — Verify: bundled, 5 consecutive Acceptance-Fast runs, 170/170 each**
      ```
      for i in 1 2 3 4 5; do
        echo "=== Run $i ==="
        tests/runner.php vendor/bin/phpunit --testsuite=Acceptance-Fast --testdox 2>&1 | grep -E 'OK \(|FAILURES|ERRORS' | tail -1
      done
      ```
      Acceptance criterion: 5 × `OK (170 tests, …)`. No flaky failures.

- [ ] **Task 4 — Verify: external-server scenario still 170/170**
      User starts `./temporal server start-dev --http-port 7243` separately, then `ALLOW_EXTERNAL_TEMPORAL_PROCESS=true tests/runner.php …`. Must still be 170/170 — the readiness probe shouldn't fight an already-warm server.

- [ ] **Task 5 — Verify: PHP unit + plugin Go test untouched**
      `composer test:unit` → 1710/1710.
      `cd roadrunner-temporal && CGO_ENABLED=0 go test ./aggregatedpool/...` → 87/87.

## Commit plan

Single commit in `sdk-php`:

```
fix(tests/acceptance): wait for worker pollers before PHPUnit dispatches

bundled RR readiness-banner fires before PHP workers complete poll
registration with Temporal. The first request hitting a queue in
that window times out at SCHEDULE_TO_*. Probe queue readiness via
`temporal task-queue describe` from `RRStarter::start()` once per
worker queue, mirroring the existing `cluster health` loop shape.
```

## Next

```
/aif-implement
```
