# Plan: Cross-SDK Parity test tier with normalized JSON comparison

**Branch:** nexus
**Date:** 2026-05-08
**Mode:** full
**Type:** new test tier + framework

## Settings

- Testing: yes — the deliverable IS the testing framework
- Logging: verbose — every normalizer accepts `?LoggerInterface` and emits DEBUG-level transitions; bash phase echoes `[parity-runner]` traces
- Docs: yes — README.md is part of the deliverable
- Roadmap linkage: skipped (no roadmap)
- Parallel implementation: each phase below has fan-out points marked **PARALLEL**; `/aif-implement` should dispatch independent tasks to subagents simultaneously

## Goal

Build a new top-level test tier — `tests/Parity/` — that compares Temporal event-history JSON snapshots captured from equivalent scenarios run on different SDKs (PHP, Java, Go). The framework normalizes per-SDK quirks (timestamps, IDs, identity strings, SDK metadata, stack-trace material, task-queue naming) so two normalized histories can be compared with a plain PHPUnit `assertEquals` as if they were two arrays.

The suite is **gitignored as a whole** while iterating. It runs in two phases: first a separate command (`make fixtures` / `./run-fixtures.sh`) walks per-scenario Makefiles to produce JSON fixtures by talking to a real Temporal dev server through both SDKs; then PHPUnit (`make assert`) loads those fixtures, normalizes them, and asserts cross-SDK parity.

## Suite name

`Parity` — chosen over the user's tentative `Integration` because:
- the existing test (`tests/Acceptance/Extra/Nexus/HistoryParity/HistoryParityTest.php`) is already named `HistoryParityTest`, so `Parity` keeps the vocabulary consistent
- "Integration" is overloaded in the PHP/Symfony/Laravel world (usually means "between modules with mocks at the edges")
- "Parity" precisely describes the deliverable: cross-implementation behavior parity at the wire-protocol level

## Layout

```
tests/Parity/                              ← entire dir gitignored
├── README.md                              ← Task #8
├── Makefile                               ← top-level convenience: fixtures, assert, all, clean
├── run-fixtures.sh                        ← Phase 1 driver, walks sub-Makefiles
├── phpunit.xml                            ← standalone phpunit config (NOT registered in root phpunit.xml.dist)
├── bootstrap.php                          ← PSR-4 autoloader for Temporal\Tests\Parity\
├── Framework/
│   ├── Source.php                         ← enum: PHP|JAVA|GO|TYPESCRIPT, with fromSdkName/fromIdentity
│   ├── EventHistory.php                   ← readonly DTO: source + events + raw
│   ├── HistoryLoader.php                  ← loadJson(path, source)
│   ├── EventNormalizerInterface.php
│   ├── FieldNormalizerInterface.php
│   ├── EventHistoryNormalizer.php         ← orchestrator
│   ├── NormalizerRegistry.php             ← public default() factory
│   ├── Field/
│   │   ├── TimestampNormalizer.php
│   │   ├── DurationNormalizer.php
│   │   ├── WorkflowIdNormalizer.php
│   │   ├── IdentityNormalizer.php
│   │   ├── TaskQueueNormalizer.php
│   │   ├── BuildIdNormalizer.php
│   │   ├── SdkMetadataNormalizer.php
│   │   └── StackTraceNormalizer.php
│   └── Sdk/
│       ├── AbstractSdkNormalizer.php
│       ├── PhpSdkNormalizer.php
│       ├── JavaSdkNormalizer.php
│       └── GoSdkNormalizer.php
└── Nexus/
    └── HistoryParity/
        ├── Makefile                       ← copied/adapted from tests/Acceptance/Extra/Nexus/HistoryParity/
        ├── scripts/
        ├── java/
        ├── php/
        ├── fixtures/                      ← .gitkeep only (JSON written by Makefile run)
        └── HistoryParityTest.php          ← demo test using the framework
```

## Two-phase execution model

**Phase 1 — fixture capture (slow, network-bound, external SDK invocations):**

```bash
cd tests/Parity
make fixtures             # or: ./run-fixtures.sh
```

`run-fixtures.sh` walks `tests/Parity/` for any file named `Makefile` (excluding the top-level driver itself), then runs `make -C <dir> all` per scenario with a 10-min timeout. Each scenario's Makefile is responsible for:
- ensuring a shared Temporal dev server, namespace, and Nexus endpoint exist (idempotent setup)
- driving its own SDKs to execute the scenario
- dumping `temporal workflow show --output json` into `<scenario>/fixtures/<sdk>-<flavor>.json`

**Phase 2 — normalize + assert (fast, pure PHP):**

```bash
cd tests/Parity
make assert               # runs `phpunit -c ./phpunit.xml --testdox`
```

PHPUnit loads each fixture via `HistoryLoader::loadJson(path, Source)`, runs `NormalizerRegistry::default()->normalize($history)`, then `assertEquals($javaNormalized, $phpNormalized)`. PHPUnit's array-diff output shows exactly which event field still has cross-SDK divergence.

`make all` runs both phases.

## Normalization model

A captured event-history JSON has two kinds of variation between SDKs:

**(a) Inherent non-determinism (always normalize away):**
- Timestamps (`eventTime`, `workflowExecutionExpirationTime`, …) → `'<TIMESTAMP>'`
- Durations (`'15s'`, `'10s'`, `'0s'`) → `'<DURATION>'`
- IDs (`workflowId`, `runId`, `requestId`, `firstExecutionRunId`, `originalExecutionRunId`, `taskId`, `scheduledEventId`, `startedEventId`) → `'<ID>'`
- Worker `identity` strings (PHP: `roadrunner:<queue>:<uuid>`, Java: `<pid>@<host>`) → `'<IDENTITY>'`
- `workerVersion.buildId` → `'<BUILD_ID>'`

**(b) SDK-attributed values (must be normalized per source):**
- Task queue name (PHP derives from class FQN, Java uses caller-provided string) → `'<TASK_QUEUE>'`
- `sdkMetadata.sdkName`/`sdkVersion`/`langUsedFlags` (PHP RR runs Go SDK so reports `temporal-go`; Java reports `temporal-java`) → fixed `{sdkName: '<SDK_NAME>', sdkVersion: '<SDK_VERSION>'}`, drop `langUsedFlags`
- Stack-trace material (PHP records `#0 …` frames inside `failure.message`; Java records nothing) → `'<STACKTRACE_PRESENT>'` / `'<STACKTRACE_ABSENT>'`

The `Source` enum picks the right `Sdk\*Normalizer` strategy at the top of the walk; that strategy delegates to shared `Field\*Normalizer` instances by field name.

## Why gitignored

The user explicitly asked for the suite to be untracked while the framework matures. This:
- avoids polluting `master` with WIP infrastructure
- avoids registering a Parity test suite in `phpunit.xml.dist` that would point to non-existent files for fresh checkouts
- keeps the per-scenario heavy artifacts (Gradle caches, captured JSON, PHP runtime files) out of git

The standalone `tests/Parity/phpunit.xml` and `tests/Parity/Makefile` make the suite self-contained — no edits to root `phpunit.xml.dist` or root `composer.json` are needed for Phase 1 to work.

A follow-up plan can later promote the framework to tracked code and register the suite in root config.

## Tasks

Tasks were created via `TaskCreate` (IDs #1–#8). Progress:

- [x] **#1** scaffold `tests/Parity/` skeleton + `.gitignore` + bootstrap
- [x] **#2** Source enum + EventHistory + HistoryLoader
- [x] **#3** Field normalizers (8 files)
- [x] **#4** SDK-specific event normalizers (4 files)
- [x] **#5** EventHistoryNormalizer + Registry orchestrator
- [x] **#6** Phase 1 fixture-runner script + top-level Makefile
- [x] **#7** Phase 2 phpunit.xml + sample HistoryParity test (passes phpunit discovery; first `assertEquals` produces the expected actionable cross-SDK diff)
- [x] **#8** README.md

Dependency wiring:

```
#1 skeleton + .gitignore
   ├─→ #2 Source enum + value objects
   │      └─→ #3 field normalizers (PARALLEL fan-out: 8 small files)
   │             └─→ #4 SDK normalizers (PARALLEL fan-out: 4 files)
   │                    └─→ #5 EventHistoryNormalizer + Registry
   │                           └─→ #7 phpunit.xml + sample test
   └─→ #6 fixture-runner (independent of framework code) ─┘
                                                          └─→ #8 README
```

**Parallel execution opportunities for `/aif-implement`:**

1. After #1 lands, #2 and #6 can run in parallel (separate concerns: data model vs bash driver).
2. Inside #3, the eight field-normalizer files have no inter-dependencies — dispatch one subagent per file, or one subagent for the batch.
3. Inside #4, the four SDK-normalizer files only share `AbstractSdkNormalizer` — implement Abstract first, then fan out PHP/Java/Go in parallel.
4. #7 and #8 can run in parallel after #5 + #6 are both done (test code vs documentation).

## Logging requirements

- All Field/Sdk normalizer constructors accept `?Psr\Log\LoggerInterface $logger = null`. When set, they emit `LogLevel::DEBUG` lines of the form `parity: {Sdk}/{Field} replaced "{before}" with "{after}" at {jsonPath}`.
- `NormalizerRegistry::default(?LoggerInterface)` propagates the logger into every nested normalizer.
- `HistoryParityTest::setUp()` reads `getenv('PARITY_DEBUG') === '1'` and, when true, installs a stderr-printing PSR-3 logger (a tiny inline anonymous class — no new dependency).
- `run-fixtures.sh` uses `set -euo pipefail` and prefixes every status line with `[parity-runner]`.

## Risks & open questions

- **Existing `tests/Acceptance/Extra/Nexus/HistoryParity/` is not removed by this plan.** The new Parity tier copies its scaffolding into `tests/Parity/Nexus/HistoryParity/`. After Phase 2 of this plan passes green, a follow-up plan should retire the Acceptance copy. Keeping both during this plan avoids accidentally breaking the working baseline.
- **`tests/Parity/Nexus/HistoryParity/Makefile` paths must be re-anchored.** The original uses `PROJECT_ROOT := $(abspath ../../../../..)` (5 levels up from `tests/Acceptance/Extra/Nexus/HistoryParity`). The new location is `tests/Parity/Nexus/HistoryParity` — also 4 levels up, so it becomes `$(abspath ../../../..)`. Verify before committing.
- **First `assertEquals` will likely fail.** Expected. The first failure surface is the documentation of which fields still need a normalizer rule. README §Debugging covers reading the diff.
- **Java fixture's stack-trace gap is intentional.** The original test asserts `assertTrue($javaHasTrace)` and fails on Java — the new framework normalizes both sides to `<STACKTRACE_PRESENT|ABSENT>`, so this divergence shows up as one explicit field difference rather than a `assertTrue` failure. The test SHOULD still surface it (we don't want to hide the bug); the README's "Adding a new parity scenario" section will note that for known gaps you can either:
  - keep the divergence visible (`assertEquals` fails until upstream fixes), OR
  - add a per-scenario `KnownDivergences` allow-list (deferred — not in this plan's scope).

## Commit Plan

5+ tasks → checkpoints every ~3 tasks:

- **Commit 1 (after #1, #2, #6):** `feat(tests): scaffold tests/Parity tier with Source/EventHistory + bash phase 1 runner`
- **Commit 2 (after #3, #4, #5):** `feat(tests/parity): per-SDK + per-field normalization framework`
- **Commit 3 (after #7, #8):** `test(parity): port HistoryParity scenario as Parity demo + README`

Note: the entire `tests/Parity/` tree is gitignored, so these commits will only show changes to `.gitignore`. The actual file content will exist in working tree only — the user will explicitly un-ignore selected paths in a future plan when ready to publish the framework.

## Next Steps

After this plan is reviewed, run `/aif-implement` to dispatch the tasks. The implement coordinator should batch independent tasks (#2 + #6, then #3's eight files, then #4's PHP/Java/Go normalizers, then #7 + #8) to subagents in parallel.

Plan file: `.ai-factory/plans/cross-sdk-parity-framework.md`
