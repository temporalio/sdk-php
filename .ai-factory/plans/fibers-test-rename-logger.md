# Plan: Rename `Logger_Test_Workflow` in the Fibers acceptance suite to break the duplicate-registration collision

**Branch:** fibers
**Date:** 2026-05-23
**Mode:** fast
**Type:** test rename / collision fix
**Source finding:** `.ai-factory/research/fibers-review.md` — C3 (CRITICAL, lines 73–84)

## Settings

- Testing: yes — both `LoggerTest` classes must continue to pass under `composer test:accept-fast`
- Docs: no — internal test fixture identifier rename, no public-facing change
- Logging: minimal — single-file rename of six string literals
- Roadmap linkage: none

## Scope statement

This plan **touches `tests/Acceptance/Extra/Workflow/Fibers/LoggerTest.php` only — safe to parallelize** with every other Fiber-fix plan. The non-Fibers `tests/Acceptance/Extra/Workflow/LoggerTest.php` is not modified — it keeps the canonical `Logger_Test_Workflow` name.

## Finding

Two acceptance tests register a workflow under the same wire name on the same task queue:

- `tests/Acceptance/Extra/Workflow/Fibers/LoggerTest.php:207` — `#[WorkflowMethod(name: "Logger_Test_Workflow")]`
- `tests/Acceptance/Extra/Workflow/LoggerTest.php:207` — `#[WorkflowMethod(name: "Logger_Test_Workflow")]`

Both nested `TestWorkflow` classes resolve to the `default` task queue via `tests/Acceptance/App/TaskQueueResolver.php` (neither class is in `SHARED_QUEUE_EXCLUSIONS`, neither carries a `#[Worker]` attribute with `pipelineProvider`/`logger`/`plugins`). The Fibers version imports `Temporal\Experiments\Fibers\Workflow` and calls `Workflow::await(...)` synchronously; the non-Fibers version imports `Temporal\Workflow` and uses `yield Workflow::await(...)`. Five `#[Stub('Logger_Test_Workflow')]` attribute occurrences in the Fibers test methods (lines 21, 45, 77, 121, 148) target the same registered name.

Net effect (per C3): either the acceptance worker fails to boot with "Workflow with name … already registered", or whichever `RuntimeBuilder` discovers last silently wins and the Fibers test exercises the wrong implementation. Either way the Fibers test provides no independent coverage.

**Decision:** rename the Fibers variant only. The non-Fibers test predates the Fibers experiment and remains the canonical owner of `Logger_Test_Workflow`. The Fibers variant becomes `Logger_Test_Fibers_Workflow`. This keeps the diff minimal (one file), preserves the non-Fibers wire name, and makes the two tests independently observable.

## Target end state

After this fix, in `tests/Acceptance/Extra/Workflow/Fibers/LoggerTest.php`:

- Line 207: `#[WorkflowMethod(name: "Logger_Test_Fibers_Workflow")]`.
- Lines 21, 45, 77, 121, 148: each `#[Stub('Logger_Test_Workflow')]` becomes `#[Stub('Logger_Test_Fibers_Workflow')]`.
- The inner PHP class name (`TestWorkflow`), the test class name (`LoggerTest`), the namespace (`Temporal\Tests\Acceptance\Extra\Workflow\Fibers\Logger`), and every other line of the file remain unchanged.

Cross-repo invariants the rename must preserve:

- Grep `Logger_Test_Fibers_Workflow` across `src/`, `tests/`, `testing/` returns exactly **six** hits — all inside `tests/Acceptance/Extra/Workflow/Fibers/LoggerTest.php` (one `WorkflowMethod` + five `Stub`).
- Grep `Logger_Test_Workflow` (whole-word, i.e. trailing `\b` so `Logger_Test_Fibers_Workflow` doesn't match) across `src/`, `tests/`, `testing/` returns exactly **six** hits — all inside `tests/Acceptance/Extra/Workflow/LoggerTest.php`.

## Tasks

- [ ] **#1** In `tests/Acceptance/Extra/Workflow/Fibers/LoggerTest.php`, replace every occurrence of the literal `Logger_Test_Workflow` with `Logger_Test_Fibers_Workflow`. There are exactly six occurrences and all are inside attribute arguments:
  - line 21 — `#[Stub('Logger_Test_Workflow')]`
  - line 45 — `#[Stub('Logger_Test_Workflow')]`
  - line 77 — `#[Stub('Logger_Test_Workflow')]`
  - line 121 — `#[Stub('Logger_Test_Workflow')]`
  - line 148 — `#[Stub('Logger_Test_Workflow')]`
  - line 207 — `#[WorkflowMethod(name: "Logger_Test_Workflow")]`

  Do NOT change the namespace, the test class name, the inner `TestWorkflow` PHP class name, or any signal/query/update method names. Do NOT add or remove imports.

- [ ] **#2** Lint and uniqueness checks:
  - `php -l tests/Acceptance/Extra/Workflow/Fibers/LoggerTest.php` → must report `No syntax errors detected`.
  - `rtk grep -rn "Logger_Test_Fibers_Workflow" src tests testing` → must return exactly six hits, all inside `tests/Acceptance/Extra/Workflow/Fibers/LoggerTest.php`.
  - `rtk grep -rn "Logger_Test_Workflow\b" src tests testing` (note the word-boundary `\b` so the Fibers name does not match) → must return exactly six hits, all inside `tests/Acceptance/Extra/Workflow/LoggerTest.php`.
  - `rtk git diff master..HEAD -- tests/Acceptance/Extra/Workflow/LoggerTest.php` (non-Fibers file) → must show **no** changes attributable to this plan.

- [ ] **#3** Behavioural verification — run the acceptance suite limited to both `LoggerTest` classes:
  - `composer test:accept-fast -- --filter=LoggerTest`
  - Expected: both the Fibers `LoggerTest` (5 tests: `loggerBasicLogging`, `loggerWithContext`, `loggerMultipleLevels`, `loggerDuringSignalProcessing`, `loggingInAllHandlers`) and the non-Fibers `LoggerTest` (same 5 tests) pass independently. With the collision removed each test exercises its own `TestWorkflow` implementation.
  - If the local environment cannot reach the Temporal server / RoadRunner needed for the fast suite, the lint + grep checks in #2 plus `composer test:arch` (architecture constraints) plus an empty diff against the non-Fibers file is acceptable evidence; flag the missing E2E run in the implementation log so the next CI run covers it.

## Optional follow-up (out of scope for this plan)

C3 suggests a meta-test that "walks all registered workflow classes and asserts uniqueness of `#[WorkflowMethod(name:)]`". That would catch this class of collision globally rather than per-rename.

**Out of scope here** because it expands the diff materially:

- Lives most naturally under `tests/Arch/` (or a new `tests/Unit/Acceptance/`) — neither directory currently has a fixture for reflecting over the acceptance-test workflow set.
- Needs to model the task-queue grouping logic in `tests/Acceptance/App/TaskQueueResolver.php` (uniqueness is per-queue, not global) — that ties the test to internal acceptance harness shape and creates a cross-cutting dependency this plan does not own.
- The collision being fixed is the only currently-known instance (C3); C4 in the same review covers a separate TaskQueue/Fibers collision under its own plan.

Track as a follow-up plan: `fibers-arch-workflow-name-uniqueness.md` (not created by this plan).

## Out of scope (explicitly)

- The non-Fibers `tests/Acceptance/Extra/Workflow/LoggerTest.php` — do not modify.
- Any change to `Logger_Test_Workflow` consumers outside the Fibers test file — there are none today; the rename is local to that one file.
- The C2 finding (Fibers tests that use `yield`). The Fibers `LoggerTest` workflow body already uses `Workflow::await(...)` without `yield`, consistent with the Fibers facade — no `yield` removal needed here. If C2's plan later finds a related issue it will be handled there.
- The C4 finding (TaskQueue/Fibers duplicate-registration collision). Tracked separately.
- Adding the meta-test described in the "Optional follow-up" section above.
- Renaming the inner PHP class `TestWorkflow` — the wire name is what matters; the PHP class symbol is already namespace-disambiguated.

## Parallelization

This plan is independent of every other Fiber-fix plan because:

- It modifies exactly one file (`tests/Acceptance/Extra/Workflow/Fibers/LoggerTest.php`) that no other Fiber-review finding touches.
- The new wire name `Logger_Test_Fibers_Workflow` is not referenced anywhere in the repository today (verified by `rtk grep -rn` over `src/`, `tests/`, `testing/`) — there is no collision risk introduced by the rename itself.
- It introduces zero new symbols, interfaces, or runtime behavior. No production code under `src/` changes.
- The verification step (`composer test:accept-fast -- --filter=LoggerTest`) does not depend on any other Fiber-fix being merged first.

**Touches `tests/Acceptance/Extra/Workflow/Fibers/LoggerTest.php` only — safe to parallelize.**

Safe to dispatch alongside C1, C2, C4, C5, C6, C7, C8 plans and all H/M/L plans.
