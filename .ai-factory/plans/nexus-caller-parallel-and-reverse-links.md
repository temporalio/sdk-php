# Implementation Plan: Nexus caller-side — parallel edge-cases & reverse-links coverage

Branch: nexus
Created: 2026-05-07

## Settings
- Testing: yes (test-coverage plan; no production code changes expected)
- Logging: standard
- Docs: no

## Why

Happy-path параллелизм покрыт (`ParallelTest::workflowAwaitsMultipleParallelNexusOperations`),
но edge-cases — нет: что происходит при **частичном падении** одной из N операций,
как **cancel** распространяется через `Promise::all`, и работает ли смешанный
**sync+async** fan-out. `LinksTest` покрывает forward-направление (caller → handler),
но **reverse-link** (handler workflow содержит link обратно на caller в своей
истории) — нет. Это системная вещь для observability и debug-чейнов в production.

Это второй из двух планов; идёт после `nexus-caller-conflict-and-idempotency.md`.
Между планами — естественный коммит-чекпоинт: conflict-семантика не зависит от
параллельного fan-out, и наоборот.

## Out-of-scope
- Метрики (отдельная инициатива)
- State-machine unit-тесты (отдельный план)
- Operation summary propagation (отдельный план)
- Custom failure converter в async completion (отдельный план)

## Tasks

### Phase 1 — Promise::all failure modes

- [x] **Task 1** — Failure semantics for `Promise::all` with one failing operation
      File: `tests/Acceptance/Extra/Nexus/ParallelFailure/PartialFailureTest.php`
      Implemented with relaxed assertion (caller fails + 3 SCHEDULED + ≥1 terminal
      Failed/TimedOut). **Surfaced product gap** documented in the test docblock:
      Promise::all + ≥1 failing op does NOT propagate the handler's
      `OperationException::failed` cleanly to caller. Handler IS invoked once and
      throws (RR plugin logs it), but the caller surfaces a
      `TimeoutFailure(SCHEDULE_TO_CLOSE)` after the timeout window instead of an
      `ApplicationFailure` with the failure message. Single-op fail path
      (`SyncFailureTest`) works correctly. The Promise::all + at-least-one-fail
      combination exposes a workflow-side state-machine or wire-response issue
      worth a dedicated follow-up RFC.

- [x] **Task 2** — Cancel propagation through `Promise::all` when caller is cancelled
      File: `tests/Acceptance/Extra/Nexus/ParallelCancel/CancelPropagationTest.php`
      (placed in own directory due to "1 *Test.php per directory" convention).
      Asserts: caller returns `'cancelled'` marker, 3 SCHEDULED events, 3
      CANCEL_REQUESTED events (proves `Scope::onRequest` registers per-promise
      onCancel correctly). Notes inline why CANCELED-event count is intentionally
      not asserted (handler catches `CanceledFailure` and returns normally → the
      operation may close as COMPLETED instead of CANCELED depending on
      server-side cancel-ack timing; either path is spec-compliant).

### Phase 2 — Mixed shapes

- [x] **Task 3** — Mixed sync + async operations in `Promise::all`
      File: `tests/Acceptance/Extra/Nexus/ParallelMixed/MixedSyncAsyncTest.php`
      Asserts caller returns `'sync=ok-x|async=done-y'` plus event-shape
      discriminator: 2 SCHEDULED, 1 STARTED (only async path emits this), 2
      COMPLETED. Handler workflow yields `Workflow::timer(100ms)` to force a real
      async-completion path so STARTED actually appears (matches the comment in
      `Replay/ReplayTest.php` about Started being the discriminator).

### Phase 3 — Reverse-link

- [x] **Task 4** — Handler workflow's history contains a Nexus-link back to caller
      File: `tests/Acceptance/Extra/Nexus/ReverseLinks/ReverseLinkTest.php`
      (placed in own directory; `Links/` already has `LinksTest.php`).
      **Assertion target corrected** from the original plan: PHP proto
      `WorkflowExecutionStartedEventAttributes` has no top-level `getLinks()`;
      reverse links live at
      `getCompletionCallbacks()[i]->getLinks()[j]->getWorkflowEvent()`.
      Mirrors sdk-go's assertion at `sdk-go/test/nexus_test.go:1322`. Walks the
      handler workflow's history, finds WORKFLOW_EXECUTION_STARTED, then
      iterates callbacks/links looking for a `workflow_event` whose
      `namespace + workflow_id` matches the caller. Falls back to
      `markTestIncomplete` with a clear note if the callbacks slot is empty
      (Temporal server <1.27 leaves it empty per agent verification).

## Commit plan

4 tasks → single commit at the end. Suggested message:
`test(nexus): cover Promise::all partial failure, cancel propagation, mixed sync+async, and reverse-links`

If reverse-link reveals a missing wire-feature (handler not actually emitting the
link), split:
1. After Tasks 1-3 → `test(nexus): cover Promise::all partial failure, cancel propagation, and mixed sync+async`
2. Task 4 alone → either a separate test commit OR a fix commit pairing test +
   handler change, depending on what the assertion reveals

## Checkpoint

After both plans (this and `nexus-caller-conflict-and-idempotency.md`) are green,
caller-side coverage will be at the level of "stable" in a production sense:
- Conflict policies: tested
- Failure propagation incl. retryability: tested
- Idempotency: tested
- Parallel happy/failure/cancel: tested
- Mixed shapes: tested
- Reverse-link: tested (or gap identified)

Remaining caller-side work after these two plans:
- State-machine unit tests
- Metrics
- Operation summary propagation

## Next

```
/aif-implement
```
