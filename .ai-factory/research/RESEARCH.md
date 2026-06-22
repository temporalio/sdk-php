# Research

Updated: 2026-05-24T13:30:00Z

## Active Summary (input for /aif-plan)
<!-- aif:active-summary:start -->
Topic: Last 3 Fibers acceptance test failures — root-cause maps + plan synthesis
Goal: close `Harness/ChildWorkflow/Fibers/CancelAbandon/childWorkflowInClosingInnerScope`, `Harness/ChildWorkflow/Fibers/Signal/SignalTest::check`, `Harness/Signal/Fibers/ChildWorkflow/ChildWorkflowTest::check`
Constraints: zero regression for Generator mode; `@experimental` boundary on `src/Experiments/Fibers/`; no `markTestSkipped`; PHPUnit/Psalm/cs:fix green
Decisions (5-subagent explore + 3-reviewer pass):
  - Plan B (typed-async proxy) — APPROVE AS-IS by all reviewers; recommended execution order: FIRST
  - Plan A (scope-cancel-propagation) — APPROVE WITH CHANGES; investigation-first; Task B.2 needs explicit pseudocode+failing test before runtime change; recommended order: SECOND
  - Two plans saved: `.ai-factory/plans/fibers-typed-proxy-async-variant.md`, `.ai-factory/plans/fibers-fix-scope-cancel-propagation.md`
  - Plan B supersedes prior skill-context rule "use untyped stub for start→signal→await" — replaced by first-class `Workflow::newAsyncChildWorkflowStub()` API
Open questions:
  - Plan A Task A logging trace not yet run; one of C1/C2/C3/C4 will be confirmed empirically
  - Reviewer R3 suggested adding a "signal handler with child workflow inside" acceptance scenario — deferred (not in current 3 failing tests, can be follow-up)
Success signals: 47/47 Harness Fibers tests green; Generator suite untouched; full test pyramid green (psalm + cs:diff + unit + arch + func + accept-fast + accept-slow)
Next step: execute `.ai-factory/plans/fibers-typed-proxy-async-variant.md` first via `/aif-implement`, then `.ai-factory/plans/fibers-fix-scope-cancel-propagation.md`
<!-- aif:active-summary:end -->

## Sessions
<!-- aif:sessions:start -->

### 2026-05-07 — Nexus typed-cause preservation in sdk-go and sdk-java

What changed: research-only, no code edits.

Key notes:

- **Wire shape (sdk-go)**: the *whole* `temporal.api.failure.v1.Failure` proto (with `application_failure_info.{type, non_retryable, details, next_retry_delay, category}`, `stack_trace`, and a recursive `cause` field) is serialised with `protojson.Marshal` and stuffed into the Nexus `Failure.details` bytes. The Nexus `Failure.message` is lifted from the proto's `message` (the proto's own `message`/`stack_trace` are blanked before marshalling to avoid duplication), and `Failure.metadata = {"type": "temporal.api.failure.v1.Failure"}`. See `sdk-go/internal/internal_nexus_task_handler.go:517-554` (`nexusFailureTypeString`, `nexusFailureMetadata`, `temporalFailureToNexusFailure`, `errorToFailure`) and `sdk-go/internal/nexus_operations.go:282-303` (handler-side `temporalFailureToNexusFailure`).

- **Wire shape (sdk-java)**: identical. `NexusUtil.exceptionToNexusFailure` converts the Throwable to a `temporal.api.failure.v1.Failure` via the data converter, then `JsonFormat.printer().print(failure.toBuilder().setMessage("").build())` produces the JSON written into `details` bytes; `Failure.metadata = {"type": "temporal.api.failure.v1.Failure"}`. See `sdk-java/temporal-sdk/src/main/java/io/temporal/internal/common/NexusUtil.java:14-66`.

- **Cause chain on the wire is recursive, NOT a flat array.** Both SDKs rely on `failurepb.Failure.cause` (a self-referential field) — i.e. the JSON in `details` is a single Failure object whose `.cause` may itself be another Failure object, all the way down. There is no separate `chain[]` envelope. PHP's current `flattenCauseChain()` shape (a top-level JSON array of `{type: PHP class, message, trace}` rows) is incompatible with both reference SDKs.

- **Reconstruction on the caller side**:
  - Go: `nexusFailureToTemporalFailure` at `sdk-go/internal/nexus_operations.go:305-333` checks `failure.Metadata["type"] == "temporal.api.failure.v1.Failure"`; if matched, `protojson.Unmarshal(failure.Details, apiFailure)` rehydrates the full proto, including the recursive cause chain. The default failure converter then walks `failure.Cause` recursively in `sdk-go/internal/failure_converter.go:201` (`failure.Cause = dfc.ErrorToFailure(errors.Unwrap(err))`) and `sdk-go/internal/failure_converter.go:241` / `failure_converter.go:281` etc. (`Cause: dfc.FailureToError(failure.GetCause())`) to rebuild a typed `ApplicationError` chain with `type`, `nonRetryable`, `details`, `nextRetryDelay`, `category` preserved at every level.
  - Java caller path: handled inside the Java test workflow service mirror at `sdk-java/temporal-test-server/src/main/java/io/temporal/internal/testservice/TestWorkflowService.java:1138-1158` (`nexusFailureToAPIFailure`) — same logic: if `metadata["type"] == FAILURE_TYPE_STRING`, parse `details` via `JsonFormat.parser().merge(...)` into a `Failure` builder.

- **Round-trip of `ApplicationError.Details`**: yes. Verified in source — `details` are populated on the way out (`sdk-go/internal/failure_converter.go:96` `Details: convertErrDetailsToPayloads(...)`) and read back on the way in (`sdk-go/internal/failure_converter.go:227` `details := newEncodedValues(applicationFailureInfo.GetDetails(), ...)` plus `Details: []interface{}{details}` at `failure_converter.go:242`). The acceptance tests assert this end-to-end at `sdk-go/test/nexus_test.go:805-813` (`OpFailedAppError`: `appErr.Type() == "TestType"`, `appErr.Details(&detail) == "foo"`), `sdk-go/test/nexus_test.go:879-885` (`OpHandlerAppError`: same), `sdk-go/test/nexus_test.go:1062-1068` (`OperationErrorOnlyCause`: `CauseType` + `"detail"`), and `sdk-go/test/nexus_test.go:1097-1116` (`OpErrorNestedCauses`: three-level chain — outer `OperationError`, middle `MiddleType`, inner `InnerType` with `inner-detail`).

- **Round-trip of `nonRetryable`**: yes — it lives on `ApplicationFailureInfo.non_retryable` inside the same proto and is read back at `sdk-go/internal/failure_converter.go:240` (`NonRetryable: applicationFailureInfo.GetNonRetryable()`). Same field is implicitly serialised by Java's `JsonFormat.printer()` since it prints the whole Failure proto.

- **Recommendation for PHP**: drop the `flattenCauseChain()` envelope entirely. Mirror sdk-go/sdk-java verbatim:
  1. Build a full `temporal.api.failure.v1.Failure` proto from the throwable, populating `application_failure_info.{type, non_retryable, details (Payloads), next_retry_delay}` and `stack_trace`. Recurse into `$throwable->getPrevious()` to fill the proto's `cause` field as another Failure.
  2. Blank `message` and `stack_trace` on the top-level proto, encode it as canonical protojson, and store the resulting bytes as `UnsuccessfulOperationError.failure.details`. Set `failure.message` to the original message and `failure.metadata = ["type" => "temporal.api.failure.v1.Failure"]`.
  3. On the caller side, when `metadata["type"]` matches, decode `details` as a `Failure` proto and rebuild the typed `ApplicationFailureException` chain by walking `cause`. PHP already has `Temporal\Api\Failure\V1\Failure` proto bindings (used elsewhere in the SDK) and the standard `FailureConverterInterface` for non-Nexus paths — reuse that converter chain for the inner Failure proto. The metadata key name `type` and the value `temporal.api.failure.v1.Failure` are the wire contract — copy them verbatim.

Links (paths):

- sdk-go/internal/failure_converter.go:66-210 (ErrorToFailure: builds typed ApplicationFailureInfo + recursive Cause)
- sdk-go/internal/failure_converter.go:213-349 (FailureToError: reconstructs typed errors + walks Cause)
- sdk-go/internal/nexus_operations.go:254 (`failureTypeString = "temporal.api.failure.v1.Failure"`)
- sdk-go/internal/nexus_operations.go:282-333 (`temporalFailureToNexusFailure`, `nexusFailureToTemporalFailure`)
- sdk-go/internal/nexus_operations.go:389-427 (`operationErrorToTemporalFailure` — caller side of OperationError)
- sdk-go/internal/internal_nexus_task_handler.go:517-554 (`nexusFailureMetadata`, `errorToFailure`, `temporalFailureToNexusFailure`)
- sdk-go/test/nexus_test.go:779-818 (`OpFailedAppError` — `TestType` + `"foo"` round-trip)
- sdk-go/test/nexus_test.go:854-890 (`OpHandlerAppError`)
- sdk-go/test/nexus_test.go:1029-1072 (`OperationErrorOnlyCause` — `CauseType` + `"detail"`)
- sdk-go/test/nexus_test.go:1075-1121 (`OpErrorNestedCauses` — three-level chain `MiddleType`/`InnerType`)
- sdk-java/temporal-sdk/src/main/java/io/temporal/internal/common/NexusUtil.java:14-66 (`exceptionToNexusFailure`)
- sdk-java/temporal-test-server/src/main/java/io/temporal/internal/testservice/TestWorkflowService.java:1133-1168 (`nexusFailureToAPIFailure`)

### 2026-05-24 — Last 3 Fibers Harness failures: 5-subagent root-cause + 3-reviewer plan synthesis

What changed: research-only; two plan files saved under `.ai-factory/plans/`. No source-code or test edits.

Key notes:

- **5 subagents** ran in parallel covering: (1) FiberScope cancel propagation timing-layer trace; (2) FiberProxy::__call auto-await mechanics; (3) Generator-mode reference flow for all 3 failing tests; (4) cross-SDK survey (Java/Go/TS) for typed start+signal+await + scope+cancel+race patterns; (5) inventory of existing Fibers async escape hatches.
- **Convergence**: subagents 2, 4, 5 independently recommended the same approach — new `FiberAsyncProxy` class + `Workflow::newAsyncChildWorkflowStub()` factory, mirroring untyped `FiberChildWorkflowStub::*Async` pattern and TS `startChild()` API.
- **Subagent 1 trace**: scope-cancel-propagation failure is a layer-timing/promise-chain gap; 4 candidates ranked (C1 layer ordering / C2 FiberScope.then delegation / C3 cancel-reset corruption / C4 asyncDetached-await-in-finally). Investigation-first plan because the exact candidate cannot be determined from static analysis.
- **3 reviewers** ran in parallel on the saved plans (API design / regression risk / completeness). Consensus: Plan B (typed-proxy) APPROVE AS-IS, low regression risk; Plan A (cancel propagation) APPROVE WITH CHANGES — Task B.2 needs explicit code spec before implementation. Recommended order: Plan B first.
- **Plans updated post-review** with: edge-case unit tests, `composer psalm` + `composer cs:diff` in acceptance criteria, explicit cross-plan independence note, explicit "supersedes prior skill-context rule" note on Plan B.

Links (paths):
- `.ai-factory/plans/fibers-typed-proxy-async-variant.md` (Plan B)
- `.ai-factory/plans/fibers-fix-scope-cancel-propagation.md` (Plan A)
- `.ai-factory/evolutions/fibers-test-replication/artifact.md` (source artifact)
- `src/Experiments/Fibers/FiberProxy.php:25-37` (auto-await site)
- `src/Experiments/Fibers/FiberScope.php` (new class, this session)
- `src/Internal/Workflow/Process/Scope.php:612` (`defer()` — layer-routing site)

<!-- aif:sessions:end -->
