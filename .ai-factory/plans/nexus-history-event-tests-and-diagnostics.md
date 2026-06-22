# Implementation Plan: History-event tests — inventory, defensive coverage, diagnostic hooks

Branch: nexus
Created: 2026-05-07

## Settings
- Testing: yes (this plan IS a test-coverage extension; output is new tests)
- Logging: standard
- Docs: no

## Why

The previous plan (`nexus-tests-rules-and-converter-fix.md`) tried to land a
production wire-shape switch and got rolled back on an undiagnosed runtime
regression. The post-mortem made one thing obvious: **we kept asserting on
caller-side exception types and missed the layer below — workflow history
events**. If we'd had history-event assertions on the rich-cause case from the
start, we'd have known immediately whether the new wire shape reached the
server or got dropped between RR and the frontend.

This plan fixes that for two audiences:

1. **For us** — tighten defensive coverage on the 6 acceptance tests already
   landed in the working tree, plus a wire-bytes golden unit test on
   `NexusFailureConverter`. These catch our own regressions early.

2. **For finding side issues** — diagnostic-grade history walks that, when
   they fail, tell you exactly which event arrived (or didn't) instead of
   generically reporting "got TimeoutFailure". This converts the
   `markTestIncomplete` carve-out into a real failing-when-broken test that
   pins down where in the pipeline the wire shape gets lost.

## Out-of-scope
- Re-landing the protojson wire shape (Tasks 3+4 of the previous plan).
  Doing the diagnostic FIRST is the prereq; the wire-shape switch comes
  back as its own plan once the diagnostic confirms PHP-side correctness.
- Fixing the `Promise::all + at-least-one-fail surfaces TimeoutFailure` gap
  separately — diagnostic test in Phase 2 will surface what the actual
  failure-event timeline looks like, but the fix itself is a different plan.
- JSON-dumped golden histories as backward-compat fixtures (paттерн C from
  `WorkflowReplayer` exploration; cf. `Versioning/ClassicTest.php`).
  Premature here — the wire shape that those fixtures would pin down is
  itself in flux. After the wire-shape plan re-lands and the rich-cause
  test goes green, dump those histories to checked-in JSON in a follow-up
  plan and use `replayFromJSON` to guard against future SDK runtime drift.
- New documentation pages.

## Affected files

```
.ai-factory/research/RESEARCH.md                                              (append session entry)

tests/Acceptance/Extra/Nexus/Idempotency/RequestIdIdempotencyTest.php          (extend with history asserts)
tests/Acceptance/Extra/Nexus/ParallelFailure/PartialFailureTest.php            (extend)
tests/Acceptance/Extra/Nexus/ParallelCancel/CancelPropagationTest.php          (extend)
tests/Acceptance/Extra/Nexus/ParallelMixed/MixedSyncAsyncTest.php              (extend)
tests/Acceptance/Extra/Nexus/ReverseLinks/ReverseLinkTest.php                  (extend)
tests/Acceptance/Extra/Nexus/SyncFailure/SyncFailureTest.php                   (extend with diagnostic)

tests/Unit/Nexus/NexusFailureConverterGoldenTest.php                           (new, golden-bytes)
```

## Tasks

### Phase 1 — Inventory: what we already cover at the history-event layer

- [ ] **Task 1** — Map all event-history-touching tests, append research entry
      File: `.ai-factory/research/RESEARCH.md` (append a new `### Sessions` entry)
      Walk every file under `tests/` that calls `getWorkflowHistory(`, references
      `EventType::EVENT_TYPE_*`, or invokes `WorkflowReplayer`. Produce a single
      table with columns: file path · which test method · which event types are
      inspected · what is asserted (count / sequence / attributes / replay) ·
      **Replayer usage** (`replayHistory` / `replayFromJSON` / `downloadHistory` /
      `replayFromServer` / `none`) · gap if any.
      The Replayer-usage column is the load-bearing addition: after the inventory
      it must be visible at a glance where the determinism-guard (paттерн A
      from prior explore session) is applied vs missing.
      Known set (verify and extend):
      - Unit: `tests/Unit/Internal/Nexus/NexusLinkConverterTestCase.php`,
        `tests/Unit/DTO/CompletionCallbackTestCase.php`,
        `tests/Unit/Internal/Client/WorkflowStarterTestCase.php`,
        `tests/Unit/Internal/Client/WorkflowStubTestCase.php`
      - Functional: `tests/Functional/SimpleWorkflowTestCase.php`,
        `tests/Functional/ReplayerTestCase.php`,
        `tests/Functional/DataConverterTestCase.php`,
        `tests/Functional/Client/FailureTestCase.php`,
        `tests/Functional/Client/UuidTestCase.php`
      - Acceptance Nexus: `Replay/ReplayTest.php` (5 tests, references
        `assertContainsEvents` helper at line 283), `AsyncCompletion/`,
        `ReverseLinks/`, `ParallelFailure/`, `ParallelMixed/`,
        `ParallelCancel/`
      - Acceptance harness: `Harness/ChildWorkflow/CancelAbandonTest.php`,
        `Harness/Update/{NonDurableRejectTest,DeduplicationTest}.php`,
        `Harness/DataConverter/EmptyTest.php`, `Extra/Activity/ActivityPausedTest.php`,
        `Extra/Stability/ResetWorkerTest.php`, `Extra/Workflow/UserMetadataTest.php`,
        `Extra/Versioning/{Classic,Deployment}Test.php`
      Output as a markdown table inside the new RESEARCH.md session entry. No
      file other than RESEARCH.md is touched in this task. Read-only review.

### Phase 2 — Diagnostic test for the unresolved rich-cause case

- [ ] **Task 2** — History-walk diagnostic that tells you exactly where the
      pipeline drops the failure
      File: `tests/Acceptance/Extra/Nexus/SyncFailure/SyncFailureTest.php`
      (extend) — add a new test method
      `richCauseHistoryDiagnoses` adjacent to the existing
      `applicationFailureCausePreservesTypeMessageAndDetails` (which stays
      `markTestIncomplete` until the wire-shape plan is re-landed).
      Behaviour:
        - Set up the existing `SyncFailureRichCauseService` /
          `RichCauseCallerWorkflow` (already wired below in the file).
        - Start the caller, wait for terminal status (PASS or FAIL).
        - Fetch caller workflow history.
        - Build an event timeline: list of `(eventId, eventType-name, key
          attribute snippet)` for every NEXUS_OPERATION_* and
          WORKFLOW_EXECUTION_FAILED event.
        - Assert exactly one of these branches is true and report which:
          - **A. Wire works**: history contains `NEXUS_OPERATION_FAILED`
            with non-empty failure attributes → the new wire shape reached
            the server. The remaining gap is caller-side reconstruction.
          - **B. Wire shape rejected**: history contains
            `NEXUS_OPERATION_TIMED_OUT` with `TIMEOUT_TYPE_SCHEDULE_TO_CLOSE`
            and zero `NEXUS_OPERATION_FAILED` → server received PHP's
            response but didn't accept it as terminal failure → bug is in
            RR or PHP wire encoding before frontend.
          - **C. RR didn't deliver**: history contains
            `NEXUS_OPERATION_SCHEDULED` only (no `_STARTED` or terminal) →
            RR plugin lost the response.
        - Test passes regardless of branch but emits a clear marker like
          `'diag:branch-A'` / `'B'` / `'C'` plus the timeline as part of the
          PHPUnit message. Goal: the next time someone tries to land the
          wire shape, this test reports WHERE the regression lives in <5
          seconds.
        - **If branch=A** (NEXUS_OPERATION_FAILED present), additionally run
          a mutation-rejection sub-test (mirrors
          `Replay/ReplayTest.php::mutatedNexusScheduledEventIsRejectedByReplayer`):
            1. Baseline: `(new WorkflowReplayer())->replayHistory($history)`
               — must complete without exception (proves history is
               replayable as-is)
            2. Mutate `failureInfo->message` (or `failureInfo.applicationFailureInfo->type`
               when the wire-shape switch lands) on the `NEXUS_OPERATION_FAILED` event
            3. `$this->expectException(ReplayerException::class);`
               then `(new WorkflowReplayer())->replayHistory($mutatedHistory);` —
               must throw
          If step 3 does NOT throw: replayer ignores the failure-event payload
          → second-order bug, surfaced as a diagnostic warning, not a test
          failure (so the diagnostic verdict still ships).
      This test is the prereq for re-landing the wire shape; do NOT
      `markTestIncomplete` it — it's a diagnostic that always produces a
      verdict.

### Phase 3 — Defensive history-event coverage on our 6 existing tests

- [ ] **Task 3** — Tighten event-shape assertions on the 6 acceptance tests
      already in the working tree
      Files: `Idempotency/RequestIdIdempotencyTest.php`,
      `ParallelFailure/PartialFailureTest.php`,
      `ParallelCancel/CancelPropagationTest.php`,
      `ParallelMixed/MixedSyncAsyncTest.php`,
      `ReverseLinks/ReverseLinkTest.php`,
      `SyncFailure/SyncFailureTest.php`.
      For each, add or strengthen history assertions AND a determinism guard:
        - **Idempotency**: assert exactly ONE handler workflow execution
          exists for the shared requestId run (use
          `WorkflowService::ListWorkflowExecutions` or describe by ID and
          check the run is the same one across both POSTs). Without this,
          the test only proves tokens match on caller side but not that the
          server actually deduped at the workflow layer.
        - **PartialFailure**: in addition to `scheduled === 3`, assert that
          for each scheduled op there is a corresponding terminal event
          (Failed OR TimedOut OR Completed). No "lost" siblings — every
          scheduled-event must have a terminal-event with the same
          `scheduledEventId`. Mirror the per-op pairing the failure-cause
          assertion was failing on.
        - **CancelPropagation**: for each `NEXUS_OPERATION_CANCEL_REQUESTED`
          assert there is a downstream terminal (Canceled OR Completed) for
          the same scheduledEventId — proves the per-op cancel-fan-out
          actually reaches a terminal state, not "hangs in cancel-requested".
        - **MixedSyncAsync**: already strong (Scheduled=2, Started=1,
          Completed=2). Add a per-op pairing check: the sync op's Scheduled
          must NOT have an intermediate Started; the async op's must.
        - **ReverseLink**: already inspects callbacks/links. Add an explicit
          assertion that the link's `getEventRef()` (when present) points at
          the caller's `NEXUS_OPERATION_SCHEDULED` event id — server-set
          plumbing, but the test currently doesn't pin which event it points
          at. Fall back to `markTestIncomplete` ONLY if eventRef is empty
          (older Temporal — already-allowed external precondition per
          CLAUDE.md), with a TODO ref to this plan.
        - **SyncFailureTest** (the existing
          `callerCatchesNexusOperationFailureWithApplicationFailureCause`):
          already passes via exception assertions. Add a parallel
          history-level test
          `callerHistoryShowsNexusOperationFailedForAppFailure` that
          fetches the caller workflow's history and asserts a
          `NEXUS_OPERATION_FAILED` event exists with a `failureInfo`
          carrying `'business-error'`. Pair test for the existing one —
          guards against the case where exception path works but history
          stops emitting the event.

      **Replay determinism guard for every test in the batch** (paттерн A from
      `Replay/ReplayTest.php`): after the event-shape assertions land, append
      `(new WorkflowReplayer())->replayHistory($history);` as the final step
      of each test. One-line addition per test, but converts "result + events
      look right" into "the entire workflow command sequence replays
      deterministically" — the strongest assertion the SDK gives us. It
      catches drifts in `Workflow::async` + `Promise::all` command-id
      ordering, payload-encoding regressions, and scheduling races that
      event-counts cannot see.
      Per-test caveats:
        - **PartialFailureTest**: caller currently terminates via the
          known TimeoutFailure(SCHEDULE_TO_CLOSE) gap, not a clean Failed
          event chain. Replay against that history may itself fail with a
          replayer error. Use `expectException(ReplayerException::class)`
          and document the link to the wire-shape gap; flip to a positive
          replay assertion once the wire-shape plan re-lands.
        - **ReverseLinkTest**: caller is trivial (one async op + return);
          replay should be a smoke check, mirrors the existing
          `asyncWorkflowRunOperationReplaysCleanly` test in
          `Replay/ReplayTest.php`.
        - **CancelPropagationTest**: this is the highest-value replay
          target — `Workflow::async` scope wrapping `Promise::all` with
          fan-out cancel is the most complex command-mix in the batch.
          Any drift in `RequestCancelNexusOperation` ID ordering surfaces
          here as a non-determinism error.

      Goal: every test in the batch fails loudly **at the history layer**
      with a per-event diagnostic dump AND has its workflow command
      sequence pinned by a determinism replay, not just at the exception
      layer.

### Phase 4 — Golden-bytes unit test for the wire encoder

- [ ] **Task 4** — Wire-bytes golden test isolated from RR / Temporal server
      File: `tests/Unit/Nexus/NexusFailureConverterGoldenTest.php` (new)
      Drives `NexusFailureConverter::operationExceptionToProto` and
      `handlerExceptionToProto` against fixed inputs, dumps the resulting
      `UnsuccessfulOperationError` / `HandlerError` proto bytes, and
      asserts:
        - `failure.metadata` matches the canonical key/value (`type` →
          `nexus.OperationError` for the legacy shape currently in HEAD;
          when the wire-shape plan re-lands, this becomes
          `temporal.api.failure.v1.Failure`)
        - `failure.message` exactly equals the OperationException message
        - `failure.details` is non-empty bytes
        - `operationState` proto field reflects `OperationState::Failed`
          / `Canceled` correctly
      For the NEW shape (when the wire-shape switch re-lands), the test
      becomes the canonical encoder regression guard: any future PHP-side
      change to `flattenCauseChain` or its replacement will fail here
      without needing a full Temporal+RR run.
      Skill-context rules apply: `*TestCase.php` suffix is wrong for
      `tests/Unit/Nexus/` paths — the existing
      `tests/Unit/Nexus/NexusTaskHandlerTestCase.php` uses `TestCase.php`,
      so match that. (Different from `tests/Nexus/Unit/`, which uses
      `*Test.php`.) Add `#[CoversClass(NexusFailureConverter::class)]`,
      and `#[UsesClass]` for every class the test constructs or asserts
      on (`OperationException`, `HandlerException`, `ErrorType`,
      `RetryBehavior`, `OperationState`, `UnsuccessfulOperationError`,
      `HandlerError`, the inner `Failure` proto).
      Verification: `composer test:unit` must keep `1711+` PASS (and grow
      by however many test methods this file adds).

## Commit plan

4 tasks → no formal phase-gated commits required, but two natural breakpoints
make review easier:

- **Commit 1 (after Task 1)**:
  `docs(research): inventory of workflow history-event coverage in the test suite`
- **Commit 2 (after Tasks 2-3)**:
  `test(nexus): add history-event assertions and rich-cause pipeline diagnostic`
- **Commit 3 (after Task 4)**:
  `test(nexus): add NexusFailureConverter wire-bytes golden test`

If the ReverseLink eventRef assertion ends up needing a separate
`markTestIncomplete` fallback, lift that out of Commit 2 into its own
short commit so reviewers see the precondition rationale clearly.

## Next

```
/aif-implement
```
