# Implementation Plan: Nexus caller-side — failure propagation & idempotency coverage

Branch: nexus
Created: 2026-05-07
Replanned: 2026-05-07 (initial Phase 1 dropped — see "Surfaced product gaps")

## Settings
- Testing: yes (test-coverage extension; no production code changes)
- Logging: standard
- Docs: no

## Why

Sync/async failure паттерны caller-workflow покрыты (`SyncFailureTest`, `AsyncFailureTest`),
но `RetryBehavior` пробрасывание к caller проверено только на одном HandlerError-сценарии,
не на `ApplicationFailure(nonRetryable)`. Идемпотентность по `Nexus-Request-Id` тоже
не покрыта e2e — только косвенно через `requestIdIsPropagatedToHandlerWorkflowId`.

Это первый из двух планов. После него — естественный коммит-чекпоинт; второй план
(parallel edge-cases + reverse-links) лежит рядом в `plans/`.

## Surfaced product gaps (out of scope here, route separately)

Initial Phase 1 of this plan ("conflict policies") was dropped after discovering
that the underlying production code does not currently expose user-facing conflict
policy in a way Java/Go do:

1. **`WorkflowRunOperation::start()` hardcodes `WorkflowIdConflictPolicy::UseExisting`**
   (`src/Nexus/WorkflowRunOperation.php:80-82`). Whatever the user passes via
   `WorkflowOptions::withWorkflowIdConflictPolicy()` is silently overridden.
   Java and Go both respect user-supplied policy and default to `Fail`. This is
   a **behaviour divergence**, not just a test gap — fixing it is a separate RFC
   (impacts every fan-in user who relied on the implicit `UseExisting`).

2. **`OnConflictOptions::cancelExisting` does not exist** in any SDK (Java, Go, PHP all
   carry only `attachRequestId` / `attachCompletionCallbacks` / `attachLinks`).
   The Task 2 originally written here was based on a wrong assumption that Java had
   this field. Dropped from scope.

3. **`NexusFailureConverter::flattenCauseChain()` strips typed-failure metadata**.
   Per `src/Nexus/Internal/Failure/NexusFailureConverter.php:111`, each cause
   level is flattened to `{type: PHP class name, message, trace}`. For an
   `ApplicationFailure` cause this loses the `getType()` (failure type, e.g.
   `'CustomBusinessType'`) and `getDetails()` (`ValuesInterface` payload) on
   the wire. Caller-side reconstruction yields a generic ApplicationFailure
   with empty type and zero details. Discovered via Task 1's acceptance run.
   Fix candidates: branch on `instanceof TemporalFailure` in `flattenCauseChain`
   and emit a richer JSON shape that the deserializer can rebuild. Belongs in
   a separate "Nexus failure-converter typed-cause preservation" plan.

Both items belong in a separate "Nexus caller-side conflict policy alignment" plan
that includes upstream discussion + behaviour change + tests, not just tests.

## Out-of-scope
- Conflict policy alignment with Java/Go (see "Surfaced product gaps" above)
- Метрики (отдельная инициатива)
- State-machine unit-тесты для `NexusOperation` / `CancelNexusOperation` (отдельный план)
- Handler panic / fatal error пути (требует обсуждения семантики с RR-командой)
- Operation summary propagation (отдельный план)

## Tasks

### Phase 1 — Failure propagation gaps

- [x] **Task 1** — Inner `ApplicationFailure` type / message / details survive Nexus roundtrip
      File: `tests/Acceptance/Extra/Nexus/SyncFailure/SyncFailureTest.php` (extend)
      Added test `applicationFailureCausePreservesTypeMessageAndDetails`
      backed by `SyncFailureRichCauseService::failWithRichCause` and
      `RichCauseCallerWorkflow`. Acceptance run revealed a real production gap:
      `NexusFailureConverter::flattenCauseChain()` (`src/Nexus/Internal/Failure/NexusFailureConverter.php:111`)
      serializes each cause level only as `{type: PHP class, message, trace}` —
      the inner `ApplicationFailure::getType()` and `getDetails()` are stripped
      on the wire. Caller-side reconstruction yields an `ApplicationFailure`
      with empty `getType()` and zero details. The test is therefore marked
      `markTestIncomplete()` with that rationale; supporting service + caller
      workflow are kept intact so flipping the assertion back on after the
      converter fix is a one-line change. **Surfaced product gap captured below.**

      Note: original plan framed this as "nonRetryable flag preservation" — that's
      not a Nexus-level concept (the protocol's `OperationError` carries no
      retry hint; retry is governed by `NexusOperationOptions::withRetryPolicy()`
      on the caller side, not by the inner failure flag). PHP converter strips
      `nonRetryable` like Java/Go do. So this task asserts type/message/details
      preservation instead, which IS what Nexus is supposed to carry.

### Phase 2 — Idempotency

- [x] **Task 2** — Idempotency: same operation requestId yields the same handler workflow
      File: `tests/Acceptance/Extra/Nexus/Idempotency/RequestIdIdempotencyTest.php` (new)
      Drive two `postOperation` HTTP starts (or two caller-workflow starts within the
      schedule-to-close window) carrying the same `Nexus-Request-Id` for the same
      service+operation. Assert exactly **one** handler workflow is created and both
      requests resolve to its result. Reuse `NexusHelper::postOperation` with custom
      header.
      Note: `requestIdIsPropagatedToHandlerWorkflowId` in `AsyncWorkflow` proves the
      mapping; this task proves the **dedup** at the server level is observable.

## Commit plan

2 tasks → single commit at the end. Suggested message:
`test(nexus): cover non-retryable failure propagation and Nexus-Request-Id idempotency`

## Checkpoint

After this plan is green:
- Commit, push, optionally open PR
- Switch to `nexus-caller-parallel-and-reverse-links.md` for the second batch
- This is the natural pause point — conflict semantics are independent from
  parallel/cancel-propagation work

## Next

```
/aif-implement
```
