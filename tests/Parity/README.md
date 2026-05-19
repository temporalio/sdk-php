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
├── go.mod / go.sum                            ← single Go module covering Scenarios/ + Runner/go-runner/
├── Framework/                                 ← comparison harness (PHP normalizers + PHPUnit base)
│   ├── Source.php                             ← enum: PHP | JAVA | GO | TYPESCRIPT
│   ├── AbstractParityScenarioTest.php         ← base PHPUnit case (pairwise Java↔PHP + Go↔PHP)
│   ├── EventHistory.php                       ← readonly DTO (source + events + raw)
│   ├── HistoryLoader.php                      ← static loadJson(path, source) + requireExists()
│   ├── EventNormalizerInterface.php
│   ├── FieldNormalizerInterface.php
│   ├── EventHistoryNormalizer.php             ← orchestrator
│   ├── NormalizerRegistry.php                 ← public default() factory
│   ├── Field/                                 ← per-field normalizers (TimestampNormalizer, …)
│   ├── Sdk/                                   ← per-SDK strategies (PhpSdkNormalizer, JavaSdkNormalizer, GoSdkNormalizer)
│   └── Templates/                             ← scenario templates used by new-scenario.sh
│       ├── scenario.env.tmpl
│       ├── ScenarioTest.php.tmpl
│       ├── php/scenario.php.tmpl
│       ├── java/…/Main.java.tmpl
│       └── go/main.go.tmpl
├── Scenarios/                                 ← all 29 scenarios live here, FLAT (no Basic/Harness split)
│   └── HelloWorld/                            ← one scenario — declarative only
│       ├── scenario.env                       ← manifest (SDKs, fixture paths, workflow type)
│       ├── HelloWorldTest.php                 ← Phase 2 PHPUnit comparison test (pure verifier)
│       ├── java/src/main/java/com/temporal/parity/Main.java   ← no per-scenario build.gradle
│       ├── php/scenario.php                   ← Workflow + parity_php_{register,run}()
│       ├── go/main.go                         ← native Go binary using go-runner
│       └── fixtures/                          ← captured *.json (gitkeep only in repo)
└── Runner/                                    ← all driver / build / lifecycle infra
    ├── Makefile                               ← top-level: build | fixtures | assert | all | temporal-{start,stop} | clean*
    ├── run-fixtures.sh                        ← Phase 1 driver (walks Scenarios/ manifests)
    ├── phpunit.xml                            ← <directory>../Scenarios</directory>
    ├── bootstrap.php                          ← PSR-4 autoloader (Framework + Scenarios)
    ├── settings.gradle / build.gradle         ← root Gradle build; `subprojects {}` applies the
    │                                             application plugin + :java-runner dep to every
    │                                             :Scenarios:<Name>:java subproject (no per-scenario build.gradle)
    ├── gradlew, gradlew.bat, gradle/          ← Gradle wrapper
    ├── java-runner/                           ← shared Java harness (Gradle subproject)
    ├── go-runner/                             ← shared Go harness (Run / RunCapturingFirstRun)
    ├── build/go-bin/                          ← go binaries land here at build time
    └── scripts/
        ├── lib/
        │   ├── log.sh                         ← parity_log / parity_die / parity_debug
        │   ├── constants.sh                   ← namespace, per-language task queues, filesystem anchors
        │   └── manifest.sh                    ← parity_load_manifest / parity_discover_scenarios
        ├── setup-temporal-once.sh             ← Phase 1 start: idempotent server start + namespace
        ├── teardown-temporal.sh               ← Phase 1 stop: kill the server ONLY if we started it (PID-marker)
        ├── setup-temporal.sh                  ← DEPRECATED shim — delegates to setup-temporal-once.sh
        ├── build.sh                           ← Phase 0: build every SDK fixture-runner
        ├── new-scenario.sh                    ← scaffold a new scenario from Framework/Templates
        ├── sdk/
        │   ├── run-java.sh                    ← gradle run, capture WORKFLOW_ID, dump JSON
        │   ├── run-php.sh                     ← CLI launcher, capture WORKFLOW_ID, dump JSON
        │   ├── run-go.sh                      ← native Go binary, capture WORKFLOW_ID, dump JSON
        │   └── dump-history.sh                ← single canonical `temporal workflow show`
        └── php/
            ├── .rr.yaml                       ← parity-only RoadRunner config (slim)
            ├── worker.php                     ← parity RR worker entrypoint
            └── run-scenario.php               ← parity CLI launcher (no PHPUnit)
```

Three top-level concerns:

- **`Framework/`** — the PHPUnit comparison harness. Each scenario's `*Test.php` extends
  `AbstractParityScenarioTest` and runs two pairwise assertions (Java↔PHP, Go↔PHP).
- **`Scenarios/`** — all scenarios as a flat list. Each folder contains only the scenario
  business logic: workflow code (`php/`, `java/`, `go/`), `scenario.env` manifest, the
  PHPUnit test entry point, and the `fixtures/` slot. No per-scenario build files.
- **`Runner/`** — every driver, build script, lifecycle helper, harness library, and
  Gradle/PHPUnit/RR config the test tier needs. Anything that's not "what the scenario
  *does*" lives here.

Two things sit at the parity root (NOT inside `Runner/`):

- `go.mod` / `go.sum` — the Go module spans both `Scenarios/<Name>/go/` (importers) and
  `Runner/go-runner/` (importee). Putting the module manifest under `Runner/` would push
  `Scenarios/` outside the module.
- `Framework/` — sibling to `Scenarios/` and `Runner/`, because it's the comparison harness
  the tests *use*, not infra the tests *run on*.

---

## Three-phase execution

### Phase 0 — build

```bash
cd tests/Parity/Runner
make build              # or: ./scripts/build.sh
```

`scripts/build.sh` discovers every `scenario.env` under `../Scenarios/`, loads
it, and for each declared SDK runs the matching build step:

- `java` → one root-level `./gradlew installDist` covering every `:Scenarios:<Name>:java` subproject
- `php`  → composer-managed (sanity-checks `vendor/bin/phpunit` exists)
- `go`   → `GOWORK=off go build -o Runner/build/go-bin/<slug> ./Scenarios/<Name>/go` per scenario
  (the `GOWORK=off` is necessary because the parent `temporalio/go.work` only declares
  `./sdk-go` for velox builds; this keeps the parity module self-contained)
- `ts` / `typescript` → reserved (manifest validation rejects them today)

**Phase 0 prerequisites for Go:** `go 1.25+`, `go.temporal.io/sdk v1.42.0`,
`github.com/google/uuid v1.6.0`. `go mod download` runs implicitly on first build.

### Phase 1 — capture fixtures

```bash
cd tests/Parity/Runner
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
cd tests/Parity/Runner
make assert
```

Runs `vendor/bin/phpunit -c tests/Parity/Runner/phpunit.xml --testdox`. Each
comparison test runs **two pairwise assertions**:

- `normalizedJavaAndPhpHistoriesMatch` — Java vs PHP (always on; required)
- `normalizedGoMatchesPhp` — Go vs PHP (auto-skipped when the scenario's
  `fixtureGo()` returns null, i.e. no Go side declared)

Each assertion calls `HistoryLoader::requireExists()` first (fails loudly
with a `make -C tests/Parity/Runner build && make -C tests/Parity/Runner fixtures`
hint when the JSON is missing), then loads both sides via
`HistoryLoader::loadJson`, normalizes through `NormalizerRegistry::default()`,
and asserts equality. PHPUnit's array-diff output points at the exact field
that still varies. (Java==PHP and PHP==Go imply Java==Go transitively — no
separate Java-vs-Go assertion is run.)

### All phases at once

```bash
cd tests/Parity/Runner && make all      # = build + fixtures + assert
```

### Where PHPUnit fits

PHPUnit only runs in Phase 2. The fixture-generator side is **not** a PHPUnit
test — it's a plain PHP CLI script booted by RoadRunner. This is enforced by:

- `Runner/scripts/php/run-scenario.php` is the only PHP entry that starts a worker
- the only `*Test.php` files under `tests/Parity/Scenarios/` are Phase 2 comparison tests
- per-scenario `scenario.php` files do not end in `Test.php` and are not picked
  up by `phpunit.xml`'s test glob

---

## Composer scripts

The three-phase flow is exposed as composer scripts:

| Composer script                | Equivalent shell command                                                                |
|--------------------------------|------------------------------------------------------------------------------------------|
| `composer test:parity:build`    | `make -C tests/Parity/Runner build` — Phase 0: build every SDK fixture-runner            |
| `composer test:parity:fixtures` | `make -C tests/Parity/Runner fixtures` — Phase 1: capture JSON fixtures                  |
| `composer test:parity:assert`   | `phpunit -c tests/Parity/Runner/phpunit.xml --testdox` — Phase 2: normalize + assert     |
| `composer test:parity`          | all three phases in order (`build` → `fixtures` → `assert`)                              |

Parity is **fully decoupled** from the Unit / Functional / Acceptance test
tiers. It has its own bootstrap (`tests/Parity/Runner/bootstrap.php`), its own phpunit
config (`tests/Parity/Runner/phpunit.xml`), and its own runtime helpers under
`tests/Parity/Framework/Runtime/` (`State`, `RRStarter`, `Bootstrap`). Running
`composer test:unit` or `composer test:accept` does **not** touch Parity, and
vice versa.

`PARITY_FILTER=HelloWorld composer test:parity` runs only the HelloWorld
scenario end-to-end (filter matches the scenario short name).

---

## Example scenarios

29 example scenarios live under `Scenarios/` as a flat list, all wired for
the three SDKs (PHP, Java, Go). They split into two origin groups:

- **22 hand-authored scenarios** (no prefix) — exercise specific Temporal building
  blocks: activities, timers, signals, queries, child workflows, side-effect,
  continue-as-new, get-version, etc. Start with `Scenarios/HelloWorld/` when
  adding a new one — it's the smallest end-to-end loop that captures a real
  Temporal event history.
- **7 cross-SDK feature ports** — names match upstream
  [`temporalio/features`](https://github.com/temporalio/features) trio features
  whose `feature.{php,java,go}` triplets translate to the parity-tier shape.

| Scenario                              | What it exercises                                                                                            | Notable history events                                            |
|---------------------------------------|--------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------|
| `HelloWorld/`                         | single-method workflow that returns `"hello, world!"`                                                        | `WORKFLOW_EXECUTION_COMPLETED`                                    |
| `Timer/`                              | `Workflow::timer()` / `Workflow.sleep()` for 1s                                                              | `TIMER_STARTED` / `TIMER_FIRED`                                   |
| `Activity/`                           | single activity call                                                                                         | `ACTIVITY_TASK_SCHEDULED/STARTED/COMPLETED`                       |
| `MultipleActivities/`                 | 3 sequential activity calls                                                                                  | activity events × 3                                               |
| `LocalActivity/`                      | single local activity (no server task)                                                                       | `MARKER_RECORDED` (local-activity marker)                         |
| `ConcurrentActivities/`               | 3 parallel activity calls via `Promise::all` / `Async.function`                                              | interleaved activity events                                       |
| `ActivityRetry/`                      | retry policy: activity always fails, exhausts `maximumAttempts=3`, workflow catches `ActivityFailure`        | `ACTIVITY_TASK_FAILED` × 3                                        |
| `ActivityTimeout/`                    | activity sleeps 5s with `startToCloseTimeout=500ms`, workflow catches `ActivityFailure`                      | `ACTIVITY_TASK_TIMED_OUT`                                         |
| `ContinueAsNew/`                      | counter workflow calls `continueAsNew` twice                                                                 | `WORKFLOW_EXECUTION_CONTINUED_AS_NEW` (in first run history only) |
| `Signal/`                             | workflow awaits a signal, client sends signal after 200ms                                                    | `WORKFLOW_EXECUTION_SIGNALED`                                     |
| `ChildWorkflow/`                      | parent workflow spawns a child, awaits its result                                                            | `START_CHILD_WORKFLOW_EXECUTION_INITIATED/STARTED/COMPLETED`      |
| `SideEffect/`                         | `Workflow.sideEffect` returning a fixed integer                                                              | `MARKER_RECORDED` (side-effect marker)                            |
| `ActivityBasicNoWorkflowTimeout/`     | upstream `activity/basic_no_workflow_timeout` — two activity calls with different timeout shapes             | activity events × 2                                               |
| `ActivityCancelTryCancel/`            | upstream `activity/cancel_try_cancel` — cancellation scope with `TRY_CANCEL` semantics                       | `ACTIVITY_TASK_CANCEL_REQUESTED/CANCELED`                         |
| `ChildWorkflowSignal/`                | upstream `child_workflow/signal` — parent spawns + signals child                                              | child workflow events + `SIGNAL_EXTERNAL_WORKFLOW_*`               |
| `QueryUnexpectedArguments/`           | upstream `query/unexpected_arguments` — typed query, valid call, finish                                       | `WORKFLOW_EXECUTION_SIGNALED` (finish) + completion                |
| `QueryUnexpectedTypeName/`            | upstream `query/unexpected_query_type_name` — driver asserts `WorkflowQueryException`                         | completion only                                                    |
| `QueryUnexpectedReturnType/`          | upstream `query/unexpected_return_type` — driver asserts `DataConverterException` on decode                   | completion only                                                    |
| `SignalExternal/`                     | upstream `signal/external` — driver sends external signal carrying a payload                                  | `WORKFLOW_EXECUTION_SIGNALED`                                      |

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
./tests/Parity/Runner/scripts/new-scenario.sh <ScenarioShort>
# e.g.
./tests/Parity/Runner/scripts/new-scenario.sh FailureRetry
```

The scaffolder fills in placeholders (`__SCENARIO_NAME__`, `__SCENARIO_SHORT__`,
`__SCENARIO_SLUG__`, `__PHP_NAMESPACE__`, `__PHP_NAMESPACE_PARENT__`,
`__WORKFLOW_TYPE__`) from `Framework/Templates/` and registers the new scenario in
`Runner/settings.gradle` as `:Scenarios:<Short>:java`. The shared
`subprojects {}` block in `Runner/build.gradle` provides the per-scenario
application config — no `<scenario>/java/build.gradle` is created. The Go side
needs no registration — `tests/Parity/go.mod` is a single module, so
`go build ./Scenarios/<Short>/go` picks up the new package automatically.
Namespace + task queues come from `Runner/scripts/lib/constants.sh` at runtime
and are *not* baked into the manifest. The generated workflow returns `"todo"`
on all three sides — flesh out the actual logic on each.

### Option B — copy an example

Copy `Scenarios/HelloWorld/` and edit:

```bash
cp -R tests/Parity/Scenarios/HelloWorld tests/Parity/Scenarios/<ScenarioShort>
```

Then in the copy edit `scenario.env`, `php/scenario.php`,
`java/src/main/java/com/temporal/parity/Main.java`, `go/main.go`, rename
`<Old>Test.php` → `<ScenarioShort>Test.php`. Also append a new
`include ':Scenarios:<ScenarioShort>:java'` line to `Runner/settings.gradle`.
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
  <(jq '.events | map(.eventType)' tests/Parity/Scenarios/HelloWorld/fixtures/java.json) \
  <(jq '.events | map(.eventType)' tests/Parity/Scenarios/HelloWorld/fixtures/php.json)
```

### Force a fresh capture

```bash
cd tests/Parity/Runner
make clean-deep             # drops *.json + java/build + .gradle + stops parity-owned Temporal server
make build fixtures assert
```

`make clean` alone only drops JSON fixtures. `make clean-build` adds removal
of `<scenario>/java/build`, `<scenario>/java/.gradle`, and `Runner/build/`.
`make clean-deep` does both and runs `teardown-temporal.sh` to stop the
parity-owned Temporal server (it leaves an externally-started server alone
thanks to the PID-marker convention).

---

## Promoting out of WIP

When the framework is stable and we're ready to commit:

1. Remove `/tests/Parity/` from `.gitignore`.
2. Optionally register the `Parity` testsuite in root `phpunit.xml.dist`
   (so `composer test` can run Phase 2 directly when fixtures are present).
3. Optionally add composer scripts:
   ```json
   "test:parity:build":    "tests/Parity/Runner/scripts/build.sh",
   "test:parity:fixtures": "tests/Parity/Runner/run-fixtures.sh",
   "test:parity:assert":   "phpunit -c tests/Parity/Runner/phpunit.xml --color=always --testdox",
   "test:parity":          ["@test:parity:build", "@test:parity:fixtures", "@test:parity:assert"]
   ```
4. Decide whether captured `*.json` fixtures should ship in the repo (tradeoff:
   reproducibility vs repo size; they are typically large).
