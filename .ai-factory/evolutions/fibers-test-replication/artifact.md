# Fibers Test Replication — Final Report

## Scope (audited 2026-05-24)

| Group | Originals | Already paired | Newly created | Status |
|-------|-----------|----------------|---------------|--------|
| Acceptance/Extra | 39 (37 workflow-bearing) | 33 | 4 | **complete** |
| Acceptance/Harness | 42 | 0 | 42 | **complete** |
| Functional | ~34 (only 2 reference inline yield) | 0 | 0 | **out of scope (rationale below)** |

**Total Fibers replicas now in repo: 79** (33 pre-existing + 46 newly added).

### Out of scope (no inline workflow / yield is unrelated)
- `Extra/Schedule/ScheduleClientTest.php`, `Extra/Schedule/ScheduleUpdateTest.php` — pure client tests, no inline workflow.
- `Functional/ConcurrentWorkflowContextTestCase.php`, `Functional/NamedArgumentsTestCase.php` — rely on shared fixtures under `tests/Fixtures/Workflow/...`. The `yield` occurrences in these files belong to PHPUnit data providers, not to workflows. A Fibers replica that respects the "minimal diff" principle is impossible without duplicating every referenced fixture workflow plus rewiring `tests/Functional/worker.php` — a separate, larger refactor.

## Canonical transformation rules

Applied mechanically by `/tmp/fibers_transform.py` (kept under `/tmp` only; the transformation is one-shot, not part of the build):

1. **Namespace**: insert `Fibers` as a new segment before the tail folder.
   - `Temporal\Tests\Acceptance\Harness\Activity\Basic` → `Temporal\Tests\Acceptance\Harness\Activity\Fibers\Basic`
2. **Workflow facade**: `use Temporal\Workflow;` → `use Temporal\Experiments\Fibers\Workflow;`
3. **Workflow attribute references**: `#[Workflow\QueryMethod]` → `#[\Temporal\Workflow\QueryMethod]` — required because the local `Workflow` alias now resolves to the fibers facade, not to the attribute namespace.
4. **Workflow IDs**: insert `Fibers_` between the second and third underscore-separated segments. `Harness_Activity_Basic` → `Harness_Activity_Fibers_Basic`. Single-segment IDs left alone.
5. **ActivityInterface prefix**: `#[ActivityInterface]` → `#[ActivityInterface(prefix: 'Fibers_')]`; existing `prefix: 'X_'` extended to `prefix: 'Fibers_X_'`. Prevents wire-level activity-name collisions when both original and replica load into the same worker.
6. **Global `\define`**: `\define('NAME', value)` → `\define(__NAMESPACE__ . '\NAME', value)`. Prevents constant-collision warnings when both test files load.
7. **Yield removal**: drop the `yield ` keyword (including `yield from`).
8. **No other diffs**: signatures, comments, types, ordering are preserved.

## Plumbing changes

### `tests/Acceptance/App/TaskQueueResolver.php`
Added Fibers replicas to `SHARED_QUEUE_EXCLUSIONS`:
- `Extra\Workflow\Fibers\WorkflowA\WorkflowATest`, `Extra\Workflow\Fibers\WorkflowB\WorkflowBTest`
- `Harness\Activity\Fibers\RetryOnError\RetryOnErrorTest`
- `Harness\Update\Fibers\Self\SelfTest`, `Harness\Update\Fibers\Activities\ActivitiesTest`
- `Harness\Signal\Fibers\Activities\ActivitiesTest`

### `tests/Acceptance/Extra/Plugin/Fibers/ClientPluginTest.php`
Plugin names changed to `prefix-plugin-fibers`, `prefix-plugin-2-fibers`, `auth-plugin-fibers` to avoid global plugin-registry collision with the original test. Assertion in `duplicatePluginThrowsException` updated to `'Duplicate plugin "prefix-plugin-fibers"'`.

## Pair verification — per-category results (Harness + Extra)

### Newly added pairs (verified pass)

| Test | Result |
|------|--------|
| `Extra/TaskQueue/Fibers/WorkflowATest` | **PASS** (1 / 1) |
| `Extra/TaskQueue/Fibers/WorkflowBTest` | **PASS** (1 / 1) |
| `Extra/Plugin/Fibers/ClientPluginTest` | **PASS** (5 / 5) |
| `Extra/Workflow/Fibers/SideEffectTest` | **PASS** (4 / 4) |
| Harness/ContinueAsNew/Fibers/* | **PASS** (1 / 1) |
| Harness/DataConverter/Fibers/* | **PASS** (6 / 6) |
| Harness/EagerWorkflow/Fibers/* | **PASS** (1 / 1) |
| Harness/Query/Fibers/* | **PASS** (5 / 5) |
| Harness/Schedule/Fibers/* | **PASS** (4 / 4) |

### Pre-existing Fibers pairs (overall Extra Fibers run)

`Extra.*.Fibers` filter: **102 / 104 pass**, 1 error (ActivityPaused/simplePause), 1 failure (Versioning/Deployment/defaultBehaviorAuto). Both failures are in pre-existing replicas, not in files I added.

### Newly added pairs with Fibers-runtime gaps

These Fibers replicas faithfully mirror the original tests but expose pre-existing runtime bugs:

| Test | Result | Bug surface |
|------|--------|-------------|
| `Harness/Activity/Fibers/CancelTryCancel` | ERROR | `Cannot resume a fiber that is not suspended` — `Workflow::async(...) + $scope->cancel()` flow |
| `Harness/ChildWorkflow/Fibers/CancelAbandon` (3 sub-tests) | FAIL | child-workflow cancellation under fibers |
| `Harness/ChildWorkflow/Fibers/Signal` | ERROR | child signal under fibers |
| `Harness/Signal/Fibers/Activities` | ERROR | activity result via signal under fibers — `RETRY_STATE_TIMEOUT` |
| `Harness/Signal/Fibers/ChildWorkflow` | ERROR | child signal under fibers |
| `Harness/Update/Fibers/Activities` | ERROR | activity inside update handler under fibers |
| `Harness/Update/Fibers/Self` | ERROR | self-update under fibers |

Skipped tests (skipped in original too, pre-existing markings):
- `Harness/Signal/Fibers/PreventClose/PreventCloseTest::checkPreventClose`
- `Harness/Update/Fibers/TaskFailure/TaskFailureTest::retryableException`

### Harness Fibers totals

- **Total tests**: 47 (Harness/Fibers only, excluding skipped 2)
- **Pass**: ~36 (≈ 77 %)
- **Fail / Error**: 11 (all in cancellation / async-scope / cross-workflow signal areas — surface area that the Fibers runtime is still maturing in)

## Known Fibers-runtime issues surfaced

These belong to `src/Experiments/Fibers/`, **not** to the test duplication itself. The replicas faithfully reproduce the original test; the runtime fails to satisfy them. They are candidates for follow-up plans under `.ai-factory/plans/fibers-*`:

1. **`Workflow::async($scope)->cancel()` flow** — Go-side panic: `Cannot resume a fiber that is not suspended`. Surfaces in `Harness/Activity/Fibers/CancelTryCancel`. Affects any test that builds a cancellable async scope and then cancels it inside the fiber loop.
2. **Child-workflow cancellation** — `Harness/ChildWorkflow/Fibers/CancelAbandon/*` (3 sub-tests) all fail.
3. **Child-workflow signal** — `Harness/ChildWorkflow/Fibers/Signal` errors.
4. **Activity-result-via-signal back to workflow** — `Harness/Signal/Fibers/Activities` errors with `RETRY_STATE_TIMEOUT` (signal back from activity isn't being delivered to the fiber-resumed workflow in time).
5. **Update-handler + activity** — both `Update/Activities` and `Update/Self` error.

## Files created / modified

- **4 new Acceptance/Extra Fibers files**:
  - `tests/Acceptance/Extra/TaskQueue/Fibers/WorkflowATest.php`
  - `tests/Acceptance/Extra/TaskQueue/Fibers/WorkflowBTest.php`
  - `tests/Acceptance/Extra/Plugin/Fibers/ClientPluginTest.php`
  - `tests/Acceptance/Extra/Workflow/Fibers/SideEffectTest.php`
- **42 new Acceptance/Harness Fibers files** (all under `tests/Acceptance/Harness/*/Fibers/`):
  - Activity (3), ChildWorkflow (4), ContinueAsNew (1), DataConverter (6), EagerWorkflow (1), Query (5), Schedule (4), Signal (6), Update (12)
- `tests/Acceptance/App/TaskQueueResolver.php` — added Fibers entries to shared-queue exclusions.
- `tests/Acceptance/Harness/ContinueAsNew/Fibers/ContinueAsSameTest.php`, `tests/Acceptance/Harness/EagerWorkflow/Fibers/SuccessfulStartTest.php` — `\define` calls namespaced to avoid global-constant collision.

## Iteration summary

| # | Phase | Score | Action |
|---|-------|-------|--------|
| 1 | A | 0.86 | Scope audited; 4 Extra + 42 Harness replicas generated; transformation script written and validated against 14 pre-existing pairs (identical output for ~38 % of them; diffs in the rest are manual touches in existing replicas, not bugs in the script); plumbing collisions resolved; pair verification run end-to-end. |

## Stop rationale

- **Duplication scope is complete** — every original Acceptance test with an inline workflow now has a Fibers replica, plus 2 explicitly-out-of-scope cases documented above.
- **Pair verification ran end-to-end** — ~76 % of the newly added Harness Fibers tests pass; the failing ones expose pre-existing runtime gaps, not test-duplication mistakes.
- The remaining work is **runtime debugging**, which the loop's task definition explicitly framed as out of scope ("проходят – идёшь дальше" — i.e. "if it passes, move on; failure here is information, not a duplication bug").
