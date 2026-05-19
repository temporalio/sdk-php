# Parity — cross-SDK behavior parity tests

A new test tier alongside `Unit`, `Functional`, `Acceptance`, `Arch`. **Asserts
that three Temporal SDKs (PHP, Java, Go) produce equivalent event histories
for the same scenario.** TypeScript stays reserved.

> **Status:** WIP. The whole `tests/Parity/` directory is **gitignored** while
> the framework matures. Promote to tracked code (and register a `Parity`
> testsuite in root `phpunit.xml.dist`) in a follow-up plan.

---

## Why a separate tier

A parity test needs to:

1. Drive **three real SDKs** (Gradle/Java + RoadRunner/PHP + native Go) end-to-end against a
   shared Temporal dev server to capture event histories.
2. Then compare those histories with full normalization of inherent and
   SDK-attributed variance (timestamps, opaque IDs, identity strings, SDK
   metadata, stack traces, task-queue naming).

That doesn't fit the existing `Acceptance` / `Functional` workflow, which
expects a single SDK process. So Parity owns its own driver Makefile, its own
PHPUnit config, its own normalization framework, and a **three-phase execution
model** (build → capture → assert). PHPUnit tests are pure verifiers: they
read JSON artifacts and assert equality. They never orchestrate SDK runs.

## Suite name choice

We call this `Parity` (not `Integration`) because:

- the deliverable is *cross-implementation parity at the wire-protocol level*;
- "Integration" is overloaded in PHP/Symfony/Laravel (usually means
  "between modules with mocks at the edges");
- the existing exemplar is already named `HistoryParityTest`.

---

## Layout

```
tests/Parity/                                  ← ENTIRE DIR is gitignored (WIP)
├── README.md                                  ← you are here
├── Makefile                                   ← top-level: build | fixtures | assert | all | clean*
├── run-fixtures.sh                            ← Phase 1 driver (walks scenario manifests)
├── phpunit.xml                                ← standalone (NOT in root phpunit.xml.dist)
├── bootstrap.php                              ← PSR-4 autoload for Temporal\Tests\Parity\
├── go.mod / go.sum                            ← single Go module rooted here
├── go-runner/                                 ← shared Go harness (Run / RunCapturingFirstRun)
├── java-runner/                               ← shared Java harness (Gradle subproject)
├── scripts/                                   ← SHARED scripts, no per-scenario duplicates
│   ├── lib/
│   │   ├── log.sh                             ← parity_log / parity_die / parity_debug
│   │   ├── constants.sh                       ← shared namespace + per-language task queues
│   │   └── manifest.sh                        ← parity_load_manifest / parity_discover_scenarios
│   ├── setup-temporal-once.sh                 ← Phase 1 start: idempotent server start + namespace
│   ├── teardown-temporal.sh                   ← Phase 1 stop: kill the server ONLY if we started it (PID-marker)
│   ├── setup-temporal.sh                      ← DEPRECATED shim — delegates to setup-temporal-once.sh
│   ├── build.sh                               ← Phase 0: build every SDK fixture-runner
│   ├── new-scenario.sh                        ← scaffold a new scenario from Framework/Skel
│   ├── sdk/
│   │   ├── run-java.sh                        ← gradle run, capture WORKFLOW_ID, dump JSON
│   │   ├── run-php.sh                         ← CLI launcher, capture WORKFLOW_ID, dump JSON
│   │   ├── run-go.sh                          ← native Go binary, capture WORKFLOW_ID, dump JSON
│   │   └── dump-history.sh                    ← single canonical `temporal workflow show`
│   └── php/
│       ├── .rr.yaml                           ← parity-only RoadRunner config (slim)
│       ├── worker.php                         ← parity RR worker entrypoint
│       └── run-scenario.php                   ← parity CLI launcher (no PHPUnit)
├── Framework/
│   ├── Source.php                             ← enum: PHP | JAVA | GO | TYPESCRIPT
│   ├── EventHistory.php                       ← readonly DTO (source + events + raw)
│   ├── HistoryLoader.php                      ← static loadJson(path, source) + requireExists()
│   ├── EventNormalizerInterface.php
│   ├── FieldNormalizerInterface.php
│   ├── EventHistoryNormalizer.php             ← orchestrator
│   ├── NormalizerRegistry.php                 ← public default() factory
│   ├── Field/                                 ← per-field normalizers (TimestampNormalizer, …)
│   ├── Sdk/                                   ← per-SDK strategies (PhpSdkNormalizer, JavaSdkNormalizer, GoSdkNormalizer)
│   └── Skel/                                  ← scenario templates used by new-scenario.sh
│       ├── scenario.env.tmpl
│       ├── ScenarioTest.php.tmpl
│       ├── php/scenario.php.tmpl
│       ├── java/…/Main.java.tmpl
│       └── go/main.go.tmpl
└── Basic/
    └── HelloWorld/                            ← scenario folder — declarative only
        ├── scenario.env                       ← manifest (SDKs, fixture paths, workflow type)
        ├── HelloWorldTest.php                 ← Phase 2 PHPUnit comparison test (pure verifier)
        ├── java/                              ← scenario-local Gradle project + sources
        ├── php/
        │   └── scenario.php                   ← Workflow + parity_php_{register,run}()
        ├── go/
        │   └── main.go                        ← native Go binary using go-runner
        └── fixtures/                          ← captured *.json (gitkeep only in repo)
```

Note: there is **no** per-scenario `Makefile` and **no** per-scenario `scripts/`
directory. All driver logic lives once in `tests/Parity/scripts/` and is
parameterized by the scenario's `scenario.env` manifest.

---

## Three-phase execution

### Phase 0 — build

```bash
cd tests/Parity
make build              # or: ./scripts/build.sh
```

`scripts/build.sh` discovers every `scenario.env` under `tests/Parity/`, loads
it, and for each declared SDK runs the matching build step:

- `java` → one root-level `./gradlew installDist` covering every scenario subproject
- `php`  → composer-managed (sanity-checks `vendor/bin/phpunit` exists)
- `go`   → `GOWORK=off go build -o build/go-bin/<slug> ./<rel>/go` per scenario
  (the `GOWORK=off` is necessary because the parent `temporalio/go.work` only declares
  `./sdk-go` for velox builds; this keeps the parity module self-contained)
- `ts` / `typescript` → reserved (manifest validation rejects them today)

**Phase 0 prerequisites for Go:** `go 1.25+`, `go.temporal.io/sdk v1.42.0`,
`github.com/google/uuid v1.6.0`. `go mod download` runs implicitly on first build.

### Phase 1 — capture fixtures

```bash
cd tests/Parity
make fixtures           # or: ./run-fixtures.sh
```

#### Shared Temporal lifecycle

`run-fixtures.sh` manages the dev server once for the whole batch (not
per-scenario). Sequence:

1. **Start (once)** — `scripts/setup-temporal-once.sh` runs at the top:
   - If `${TEMPORAL_ADDRESS}` (`127.0.0.1:7233` by default) is **already
     listening**, leave it alone — useful when you have a dev server running in
     another terminal.
   - Otherwise spawn `temporal server start-dev` in the background, wait up to
     10s for the port to come up, and write the child PID to
     `/tmp/temporal-parity/server.pid`. The PID-marker is the *only* signal
     `teardown-temporal.sh` uses to decide whether to stop the server.
   - In both cases, ensure the shared `parity` namespace exists (idempotent).
2. **Stop (once)** — `scripts/teardown-temporal.sh` runs on EXIT / INT / TERM
   via a `trap`. If `/tmp/temporal-parity/server.pid` does **not** exist (the
   server was already up when we started), it is a no-op — your external dev
   server survives. If the marker exists, SIGTERM then SIGKILL after 5s grace.

Two convenience targets expose the lifecycle for direct use:

```bash
make temporal-start     # start + ensure namespace
make temporal-stop      # stop iff we started it
```

#### Shared namespace and per-language task queues

A single namespace `parity` and three per-SDK task queues
(`parity-php-task-queue`, `parity-java-task-queue`, `parity-go-task-queue`) are
used across all scenarios. The source of truth is
`scripts/lib/constants.sh`; each value is overridable via env (`PARITY_NAMESPACE`,
`PARITY_PHP_TASK_QUEUE`, …) — useful for CI partitioning. Per-scenario
`NAMESPACE=` and `TASK_QUEUE=` lines used to live in `scenario.env`; they have
been removed (and `manifest.sh` warns if a stray scenario keeps them).

#### Per-scenario execution

After Temporal is up, `run-fixtures.sh` discovers every `scenario.env` and for
each declared SDK in `SDKS=` invokes `scripts/sdk/run-<sdk>.sh <scenario-dir>`.
Each SDK runner:

- picks the shared namespace + the matching per-language task queue from
  `lib/constants.sh`
- executes the scenario as a real worker on that queue
- prints `WORKFLOW_ID=<id>` on stdout
- delegates to `scripts/sdk/dump-history.sh` to capture
  `temporal workflow show --output json` into the manifest's `FIXTURE_*` path

Per-scenario timeout: 600s by default, override with
`PARITY_FIXTURE_TIMEOUT=<seconds>`.

The PHP runner (`scripts/sdk/run-php.sh`) goes through `scripts/php/run-scenario.php`,
which spins up the parity-only RoadRunner worker (`scripts/php/worker.php` +
`scripts/php/.rr.yaml`). PHPUnit is **not** in this pipeline.

### Phase 2 — normalize + assert

```bash
cd tests/Parity
make assert
```

Runs `vendor/bin/phpunit -c tests/Parity/phpunit.xml --testdox`. Each
comparison test runs **two pairwise assertions**:

- `normalizedJavaAndPhpHistoriesMatch` — Java vs PHP (always on; required)
- `normalizedGoMatchesPhp` — Go vs PHP (auto-skipped when the scenario's
  `fixtureGo()` returns null, i.e. no Go side declared)

Each assertion calls `HistoryLoader::requireExists()` first (fails loudly
with a `make -C tests/Parity build && make -C tests/Parity fixtures` hint
when the JSON is missing), then loads both sides via
`HistoryLoader::loadJson`, normalizes through `NormalizerRegistry::default()`,
and asserts equality. PHPUnit's array-diff output points at the exact field
that still varies. (Java==PHP and PHP==Go imply Java==Go transitively — no
separate Java-vs-Go assertion is run.)

### All phases at once

```bash
cd tests/Parity && make all      # = build + fixtures + assert
```

### Where PHPUnit fits

PHPUnit only runs in Phase 2. The fixture-generator side is **not** a PHPUnit
test — it's a plain PHP CLI script booted by RoadRunner. This is enforced by:

- `scripts/php/run-scenario.php` is the only PHP entry that starts a worker
- the only `*Test.php` files under `tests/Parity/` are Phase 2 comparison tests
- per-scenario `scenario.php` files do not end in `Test.php` and are not picked
  up by `phpunit.xml`'s test glob

---

## Composer scripts

The three-phase flow is exposed as composer scripts:

| Composer script                | Equivalent shell command                                                       |
|--------------------------------|--------------------------------------------------------------------------------|
| `composer test:parity:build`    | `make -C tests/Parity build` — Phase 0: build every SDK fixture-runner          |
| `composer test:parity:fixtures` | `make -C tests/Parity fixtures` — Phase 1: capture JSON fixtures                |
| `composer test:parity:assert`   | `phpunit -c tests/Parity/phpunit.xml --testdox` — Phase 2: normalize + assert   |
| `composer test:parity`          | all three phases in order (`build` → `fixtures` → `assert`)                     |

Parity is **fully decoupled** from the Unit / Functional / Acceptance test
tiers. It has its own bootstrap (`tests/Parity/bootstrap.php`), its own phpunit
config (`tests/Parity/phpunit.xml`), and its own runtime helpers under
`tests/Parity/Framework/Runtime/` (`State`, `RRStarter`, `Bootstrap`). Running
`composer test:unit` or `composer test:accept` does **not** touch Parity, and
vice versa.

`PARITY_FILTER=Basic/HelloWorld composer test:parity` runs only the
HelloWorld scenario end-to-end.

---

## Example scenarios

29 example scenarios ship with the framework, all wired for the three SDKs
(PHP, Java, Go), in two areas:

- `Basic/` — 22 scenarios, hand-authored to exercise specific Temporal building
  blocks (activities, timers, signals, queries, child workflows, side-effect,
  continue-as-new, get-version, etc.). Start with `Basic/HelloWorld` when adding
  a new one — it's the smallest end-to-end loop that captures a real Temporal
  event history.
- `Harness/` — 7 scenarios ported from the upstream
  [`temporalio/features`](https://github.com/temporalio/features) cross-SDK
  feature set. Each one traces 1:1 to `features/features/<area>/<name>/feature.{php,java,go}`.
  See "Cross-SDK feature ports (Harness/)" below.

| Path                                | What it exercises                                                                                            | Notable history events                                            |
|-------------------------------------|--------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------|
| `Basic/HelloWorld/`                 | single-method workflow that returns `"hello, world!"`                                                        | `WORKFLOW_EXECUTION_COMPLETED`                                    |
| `Basic/Timer/`                      | `Workflow::timer()` / `Workflow.sleep()` for 1s                                                              | `TIMER_STARTED` / `TIMER_FIRED`                                   |
| `Basic/Activity/`                   | single activity call                                                                                         | `ACTIVITY_TASK_SCHEDULED/STARTED/COMPLETED`                       |
| `Basic/MultipleActivities/`         | 3 sequential activity calls                                                                                  | activity events × 3                                               |
| `Basic/LocalActivity/`              | single local activity (no server task)                                                                       | `MARKER_RECORDED` (local-activity marker)                         |
| `Basic/ConcurrentActivities/`       | 3 parallel activity calls via `Promise::all` / `Async.function`                                              | interleaved activity events                                       |
| `Basic/ActivityRetry/`              | retry policy: activity always fails, exhausts `maximumAttempts=3`, workflow catches `ActivityFailure`        | `ACTIVITY_TASK_FAILED` × 3                                        |
| `Basic/ActivityTimeout/`            | activity sleeps 5s with `startToCloseTimeout=500ms`, workflow catches `ActivityFailure`                      | `ACTIVITY_TASK_TIMED_OUT`                                         |
| `Basic/ContinueAsNew/`              | counter workflow calls `continueAsNew` twice                                                                 | `WORKFLOW_EXECUTION_CONTINUED_AS_NEW` (in first run history only) |
| `Basic/Signal/`                     | workflow awaits a signal, client sends signal after 200ms                                                    | `WORKFLOW_EXECUTION_SIGNALED`                                     |
| `Basic/ChildWorkflow/`              | parent workflow spawns a child, awaits its result                                                            | `START_CHILD_WORKFLOW_EXECUTION_INITIATED/STARTED/COMPLETED`      |
| `Basic/SideEffect/`                 | `Workflow.sideEffect` returning a fixed integer                                                              | `MARKER_RECORDED` (side-effect marker)                            |

### Cross-SDK feature ports (`Harness/`)

Each `Harness/` scenario traces 1:1 to an upstream
`temporalio/features/features/<upstream-path>/feature.{php,java,go}` triplet.
Workflow type names follow `Parity_Harness_<ScenarioShort>` and the workflow
shape mirrors what the upstream features assert, adapted to the parity tier's
"single workflow, single captured history" shape.

| Path                                            | Upstream feature                                       | What it exercises                                                                                                |
|-------------------------------------------------|--------------------------------------------------------|------------------------------------------------------------------------------------------------------------------|
| `Harness/ActivityBasicNoWorkflowTimeout/`       | `activity/basic_no_workflow_timeout`                   | Two sequential activity calls with different timeout shapes (`scheduleToCloseTimeout`, `startToCloseTimeout`)    |
| `Harness/ActivityCancelTryCancel/`              | `activity/cancel_try_cancel`                           | Cancellation scope around an activity with `TRY_CANCEL` semantics; workflow catches the cancellation             |
| `Harness/ChildWorkflowSignal/`                  | `child_workflow/signal`                                | Parent spawns a child workflow, signals it, returns child result                                                  |
| `Harness/QueryUnexpectedArguments/`             | `query/unexpected_arguments`                           | Typed query handler; driver issues a valid query, verifies result, finishes workflow                              |
| `Harness/QueryUnexpectedTypeName/`              | `query/unexpected_query_type_name`                     | Workflow has no query handler with the queried name; driver asserts a `WorkflowQueryException`                    |
| `Harness/QueryUnexpectedReturnType/`            | `query/unexpected_return_type`                         | Query returns `string`, driver decodes as `int`, asserts `DataConverterException`                                |
| `Harness/SignalExternal/`                       | `signal/external`                                      | Workflow awaits an external signal carrying a string payload; driver sends it after start                         |

Deferred (cross-SDK features that don't fit the parity tier today; tracked as follow-ups):

- `data_converter/*` (6 features) — needs an external codec-server fixture + payload codec round-trip wiring
- `schedule/*` (4 features) — Schedules don't produce a single workflow history; capture path would need `temporal schedule describe`
- `update/*` (9 features) — needs Update API on the dev server + an Update-aware driver shape on each runner
- `eager_activity/non_remote_activities_worker` — needs PHP/RR to expose `local_activity_worker_only` worker option
- `query/timeout_due_to_no_active_workers` — needs a controlled worker-stop hook on every SDK runner (PHP runner via RR is the gap)

---

## Adding a new parity scenario

### Option A — scaffolder

```bash
./tests/Parity/scripts/new-scenario.sh <Area> <ScenarioShort>
# e.g.
./tests/Parity/scripts/new-scenario.sh Basic FailureRetry
```

The scaffolder fills in placeholders (`__SCENARIO_NAME__`, `__SCENARIO_SHORT__`,
`__SCENARIO_SLUG__`, `__AREA__`, `__PHP_NAMESPACE__`, `__PHP_NAMESPACE_PARENT__`,
`__WORKFLOW_TYPE__`) from `Framework/Skel/basic/` and registers the new scenario
in the root `settings.gradle` (Java is a multi-project Gradle build). The Go side
needs no registration — `tests/Parity/go.mod` is a single module, so
`go build ./...` picks up the new `<scenario>/go/main.go` automatically.
Namespace + task queues are *not* baked into the manifest anymore — they come
from `scripts/lib/constants.sh` at runtime. The generated workflow returns
`"todo"` on all three sides — flesh out the actual logic on each.

### Option B — copy an example

Copy `Basic/HelloWorld/` and edit:

```bash
cp -R tests/Parity/Basic/HelloWorld tests/Parity/Basic/<ScenarioShort>
```

Then in the copy edit `scenario.env`, `php/scenario.php`,
`java/src/main/java/com/temporal/parity/Main.java`, `go/main.go`, rename
`<Old>Test.php` → `<ScenarioShort>Test.php`. Also append a new
`include ':Basic:<ScenarioShort>:java'` line to the root `settings.gradle`.
Run `composer test:parity` to capture + assert.

---

## Normalizer model

A captured event-history JSON has two kinds of variance to neutralize:

### (a) Inherent non-determinism — always normalize

| Field type            | Example values                        | Placeholder            |
|-----------------------|---------------------------------------|------------------------|
| RFC 3339 timestamp    | `2026-05-08T16:34:46.792Z`            | `<TIMESTAMP>`          |
| protojson duration    | `15s`, `0s`, `1.500000001s`           | `<DURATION>`           |
| Opaque IDs            | UUIDs, monotonic taskId/eventId       | `<ID>`                 |
| Worker identity       | `roadrunner:<queue>:<uuid>` / `<pid>@<host>` | `<IDENTITY>`    |
| `workerVersion.buildId` | hex blob                            | `<BUILD_ID>`           |

### (b) SDK-attributed values — normalize per `Source`

| Field                       | PHP                       | Java               | Go                | Normalization                              |
|-----------------------------|---------------------------|--------------------|-------------------|--------------------------------------------|
| `taskQueue.name`            | `parity-php-task-queue`   | `parity-java-task-queue` | `parity-go-task-queue` | rewrite `name` to `<TASK_QUEUE>` (sourced from `scripts/lib/constants.sh`) |
| `sdkMetadata`               | `temporal-go` (RR runs Go) | `temporal-java`   | `temporal-go`     | collapse to `{sdkName, sdkVersion}` placeholders, drop `langUsedFlags` |
| `failure.message` / `failure.stackTrace` | `#0 …` PHP frames embedded | usually empty | Go-style frames embedded | replace with `<STACKTRACE_PRESENT>` or `<STACKTRACE_ABSENT>` |

### Wiring

The `Source` enum picks an `Sdk\*Normalizer` strategy at the top of each
walk; that strategy delegates field-by-field to the shared `Field\*` rules
listed in `NormalizerRegistry::default()`.

---

## Extending normalizers

When a new scenario surfaces a field that's non-deterministic or
SDK-attributed and not yet collapsed:

1. **First-run diff identifies it.** PHPUnit's array diff shows the field
   path and both values. Decide: inherent noise vs real divergence.
2. **If noise** — add the leaf-key name to `NormalizerRegistry::default()`'s
   `$sharedRules` map, mapped to the appropriate `Field\*` normalizer (or
   create a new one for a new value shape).
3. **If SDK-attributed** — override `additionalFieldRules()` (or
   `dropKeys()`) on the relevant `Sdk\*Normalizer` subclass.
4. **If real upstream divergence** — leave it surfacing. The failing
   `assertEquals` documents an actual bug or feature gap; close it with an
   upstream fix (or scope the assertion explicitly).

---

## Debugging

### Verbose output

Set `PARITY_DEBUG=1` to enable:

- bootstrap announcement
- fixture-loader trace lines
- per-field DEBUG logs for every normalizer rewrite
- normalized event counts per side

```bash
PARITY_DEBUG=1 vendor/bin/phpunit -c tests/Parity/phpunit.xml --testdox
```

### Inspect a single fixture diff manually

```bash
diff \
  <(jq '.events | map(.eventType)' tests/Parity/Basic/HelloWorld/fixtures/java.json) \
  <(jq '.events | map(.eventType)' tests/Parity/Basic/HelloWorld/fixtures/php.json)
```

### Force a fresh capture

```bash
cd tests/Parity
make clean-deep             # drops *.json + java/build + .gradle + kills Temporal dev server
make build fixtures assert
```

`make clean` alone only drops JSON fixtures. `make clean-build` adds removal
of `<scenario>/java/build` and `<scenario>/java/.gradle`. `make clean-deep`
does both and also `pkill`s the local `temporal server start-dev` (skip if
you started Temporal elsewhere).

---

## Promoting out of WIP

When the framework is stable and we're ready to commit:

1. Remove `/tests/Parity/` from `.gitignore`.
2. Optionally register the `Parity` testsuite in root `phpunit.xml.dist`
   (so `composer test` can run Phase 2 directly when fixtures are present).
3. Optionally add composer scripts:
   ```json
   "test:parity:build":    "tests/Parity/scripts/build.sh",
   "test:parity:fixtures": "tests/Parity/run-fixtures.sh",
   "test:parity:assert":   "phpunit -c tests/Parity/phpunit.xml --color=always --testdox",
   "test:parity":          ["@test:parity:build", "@test:parity:fixtures", "@test:parity:assert"]
   ```
4. Decide whether captured `*.json` fixtures should ship in the repo (tradeoff:
   reproducibility vs repo size; they are typically large).
