# Cross-SDK Code Review — Nexus subsystem

**Scope reviewed:**

- `sdk-php` branch `nexus` vs `master` — 50 commits, 232 files, +19873 / −50 lines.
  99 non-test source files in `src/Nexus/`, `src/Internal/Nexus/`,
  `src/Internal/Workflow/`, `src/Workflow/`.
- `roadrunner-temporal` branch `nexus` vs `master` — 9 commits, 22 files,
  +6394 / −15 lines.

**Reference notes used** (under `.ai-factory/research/nexus/`):
`spec.md`, `sdk-go.md`, `sdk-java.md`, `sdk-typescript.md`, `samples.md`,
`cross-sdk.md`, `roadrunner-temporal.md`, plus the existing project docs
(`docs/nexus/spec.md`, `docs/nexus/rr-integration.md`,
`docs/nexus/handler-side-sdk.md`) and `nexus_plan.md`.

**Risk level:** 🟡 **Medium.** Two ERRORs in the failure-conversion stream
that affect cross-SDK compatibility on inbound errors; multiple WARNs around
hardening (registry growth, facade context restoration, test gaps). Wire
shapes are consistent end-to-end.

## Context gates

| Gate | Status | Notes |
|---|---|---|
| Architecture (`.ai-factory/ARCHITECTURE.md`) | ✅ OK | Previous audit (completed `PLAN.md`) already enforced layering rules; no fresh boundary violations spotted. |
| Rules (`.ai-factory/rules/base.md`) | ✅ OK | Strict types, naming, no essay comments — maintained across new code. |
| Roadmap | ⚠ WARN | No `.ai-factory/ROADMAP.md` to link against; not blocking. |

## Critical issues (ERROR)

### E1 — gRPC code → HandlerErrorType table is missing on PHP

**Files:** none on PHP side (the table simply does not exist).
**Reference:** `sdk-go/internal/internal_nexus_task_handler.go:612-647`,
`sdk-java/.../NexusTaskHandlerImpl.java:131-143, 234`.

When a Temporal gRPC service-error bubbles inside a Nexus handler (e.g.
`start()` raises `WorkflowExecutionAlreadyStarted` → gRPC `AlreadyExists`),
Go translates it to `HandlerError{Internal, NonRetryable}`. PHP currently
lets such exceptions escape un-mapped — they hit the generic catch in
`NexusTaskHandler` only as `HandlerException`; a `ServiceClientException`
or similar is neither caught nor mapped.

The Go table (must be ported verbatim):

| gRPC code | HandlerErrorType | Retry behavior |
|---|---|---|
| `InvalidArgument` | `BadRequest` | inherit |
| `AlreadyExists`, `FailedPrecondition`, `OutOfRange` | `Internal` | NonRetryable |
| `Aborted`, `Unavailable` | `Unavailable` | inherit |
| `Canceled`, `DataLoss`, `Internal`, `Unknown`, `Unauthenticated`, `PermissionDenied` | `Internal` | inherit |
| `NotFound` | `NotFound` | inherit |
| `ResourceExhausted` | `ResourceExhausted` | inherit |
| `Unimplemented` | `NotImplemented` | inherit |
| `DeadlineExceeded` | `UpstreamTimeout` | inherit |

Note: Go intentionally collapses `Unauthenticated` / `PermissionDenied` to
`Internal` — replicate verbatim.

**Action:** add `Internal\Nexus\GrpcErrorMapper` with this table; call from
`NexusTaskHandler::handleStartOperation`/`handleCancelOperation`'s
catch-all.

### E2 — No consume-side reader for inbound HandlerError details

**Files:** PHP has no path that turns a wire `HandlerError` proto into a
`HandlerException`. The canonical
`HandlerErrorFailure::readErrorType()` / `readRetryableOverride()` from the
nexus-rpc protocol SDK (`/Users/xepozz/IdeaProjects/temporalio/nexus-rpc-sdk-php/src/Exception/HandlerErrorFailure.php:104-113`)
is unused by the Temporal SDK. Mirror gap for `OperationException`.

PHP today only **produces** `HandlerError`. The bidirectional contract
(consume an inbound HandlerError, surface to the caller as
`HandlerException` with the right `retryBehavior`) is missing.

**Action:**

```php
// In NexusFailureConverter
public function handlerExceptionFromProto(HandlerError $proto): HandlerException
{
    $type = ErrorType::tryFrom($proto->getErrorType()) ?? ErrorType::Unknown;
    $retry = $proto->hasRetryableOverride() ? RetryBehavior::from(...) : null;
    return HandlerException::fromCause($type, ..., $retry);
}
```

Mirror for `OperationException::from(UnsuccessfulOperationError)`.

## Warnings (WARN)

Grouped by area. None are blockers but each is worth addressing.

### Failure conversion / token

- **W1 — Recursive `cause` flattened to `details._traceback`.**
  `src/Nexus/Internal/Failure/NexusFailureConverter.php:104-124` flattens the
  cause chain into a Temporal-only `_traceback` key. The proto wire has no
  recursive `cause`, so flattening is necessary, but Java/Go consumers
  ignore this key. Consider renaming to `_temporalCauseChain` to make the
  Temporal-private status explicit, or mirror `FailureInfo.cause` from the
  protocol SDK.

- **W2 — No non-retryable ApplicationError shortcut.**
  `src/Internal/Nexus/NexusTaskHandler.php:170-174`: Go's `convertKnownErrors`
  (Go ref line 595-602) takes a non-retryable `ApplicationError` and re-emits
  as `HandlerError(Internal, NonRetryable)`. PHP just lets `ApplicationFailure`
  propagate; the NonRetryable signal is lost. Add an
  `\Temporal\Exception\Failure\ApplicationFailure` catch that inspects
  `nonRetryable`.

### Service registration / handler dispatch

- **W3 — `#[AsyncOperation]` without `#[OperationCancel]` not rejected at
  registration.** `src/Internal/Declaration/Reader/NexusServiceReader.php:383-442`
  validates discovered cancel methods (orphan target, sync target, dup name)
  but does NOT walk async operations to confirm each has a paired cancel.
  Failure shows up only at runtime in
  `MethodOperationHandler::cancel()` (`src/Nexus/Handler/Internal/MethodOperationHandler.php:57-68`)
  as `HandlerException(NotImplemented, "...does not declare a cancel routine")`.
  After `bindCancelHandlers()`, when `$reflection` is a concrete impl class,
  assert every async-operation prototype has a non-null `cancelHandler`.

- **W4 — Facade static slot `finally`-clears (does not restore) prior
  context.** `src/Nexus/Handler/Internal/ServiceHandler.php:120-140, 178-196`
  + `src/Internal/Support/Facade.php:25-46`. `Nexus::setCurrentContext()`
  writes to the same slot used by `Workflow`/`Activity`. The `finally` blocks
  set it to `null`, not back to the previous value. Today the dispatch
  entry points (`InvokeNexusOperation` route, `NexusTaskHandler::handleStartOperation`)
  are top-level so the leak risk is theoretical — but the pattern is
  fragile and matches `InvokeActivity.php:118` (project-wide style).
  Capture-and-restore in `finally`, or add an arch test pinning that no two
  facade contexts can be live at once.

- **W5 — `MethodCanceller` cannot fire while a sync handler runs.**
  PHP's worker loop is single-threaded.
  `src/Internal/Transport/Router/InvokeNexusOperation.php:54-90` registers
  the canceller, calls `startOperationDirect()` synchronously, then
  unregisters in `finally`. The cancel route can only be dispatched between
  RR commands, never *during* the start call. The
  `OperationContext::isMethodCancelled()` peer-cancel branch is effectively
  dead code; only the deadline path can flip it. Document this on
  `MethodCanceller` / `OperationContext::addMethodCancellationListener`,
  or remove the unused peer-cancel branch.

### Caller-side wire

- **W6 — Stale memory note about caller-wire design.**
  `project_nexus_caller_wire_design.md` says RR responds to a single
  `ExecuteNexusOperation` with `{kind:sync,payloads}` or `{kind:async,token}`,
  then async fetches result via `AwaitNexusOperationResult{id}`. The actual
  implementation uses `GetNexusOperationStarted` listener + the original
  `ExecuteNexusOperation` future carrying the final result (closer to the
  child-workflow split). Information is equivalent, wire shape is different.
  **Update the memory note** so future planning matches reality.
  PHP: `src/Internal/Workflow/NexusOperationStub.php:69-89`.
  Go: `roadrunner-temporal/aggregatedpool/handler.go:533-567`,
  `aggregatedpool/nexus_caller.go:13-66`.

- **W7 — `NexusServiceProxy` doesn't `implements` the target service interface.**
  `src/Internal/Workflow/NexusServiceProxy.php:23-50` extends `Proxy` with
  `__call`. Callers can't write `private SampleNexusService $stub;` —
  must use `private object $stub;` with `@var SampleNexusService` PHPDoc
  hint (see `samples-php/.../EchoCallerWorkflowImpl.php:24`). This is
  consistent with `ChildWorkflowProxy`/`ActivityProxy`, so it's project
  precedent, not a new wart. Document the `object + @var` pattern
  explicitly in `NexusServiceProxy` PHPDoc so future contributors don't try
  to make it `implements`-able (the workflow methods on the interface
  return non-promise types but the proxy returns `PromiseInterface`, so a
  generated implementer would lie about return types).

- **W8 — Tautological wire-shape test.**
  `tests/Unit/Internal/Transport/Request/GetNexusOperationStartedTestCase.php:22-28`'s
  `testCarriesStartIdInOptions` reads back constructor args. Per CLAUDE.md
  "Tests must exercise real behaviour", extend it to also assert
  `json_encode($request->getOptions()) === '{"id":123}'` so the wire-shape
  claim is anchored against id-as-string or zero-coercion regressions.

### roadrunner-temporal hardening

- **W9 — `NexusStartedRegistry` has no `Discard`; orphan entries pile up.**
  `roadrunner-temporal/registry/registry.go:13-47`,
  `aggregatedpool/nexus_caller.go:18-32`. `Registry.Push` stores entries in
  `sync.Map` and never removes them; `Listen` doesn't delete either. Per-instance
  GC mitigates, but long-lived sticky workflow instances with thousands of
  Nexus ops will accumulate entries — every Nexus op (sync or async) goes
  through this registry per spec
  (`.ai-factory/research/nexus/spec.md:60-90`).
  Add `Discard(id)` and call from completion in `nexus_caller.go:36` after
  `wp.mq.PushResponse`, mirroring the existing `wp.canceller.Discard(startMsgID)`.

- **W10 — Registry runs Listen callbacks under `c.Lock()`.**
  `roadrunner-temporal/registry/registry.go:31-35, 42-45`. The listener
  invocation happens under the registry mutex, serializing all Nexus traffic
  on the registry. Drop the lock around listener invocation (or copy the
  callback before invoking) so listeners execute lock-free.

- **W11 — `_rr_nexus_kind` not stripped from sync-result payload metadata.**
  `roadrunner-temporal/aggregatedpool/nexus.go:222-229, 247-281`.
  `extractNexusLinks` deletes `_rr_nexus_links` even on parse failure (good).
  `_rr_nexus_kind` is not stripped before forwarding sync results. Today
  it isn't set on sync paths, but if PHP ever emits it the value would leak
  to the caller. One-line defensive fix:
  `delete(md, nexusKindMetadataKey)` after `isAsyncPayload(p)` test, before
  returning sync result.

- **W12 — `seqID` / `invocationSeq` divergence is silent and brittle.**
  `roadrunner-temporal/aggregatedpool/nexus.go:51-52, 140-152, 419`. Two
  separate counters are kept in step everywhere except `cancelOperation`,
  which increments only `seqID`. The test
  `nexus_handler_test.go:353-368` asserts `cmd.InvocationID == codec.encodedMsg.ID`,
  passing only because both start at 0 and increment together. After a
  cancel, `invocationSeq` falls behind. PHP correlation works (PHP only
  sees `InvocationID`), but the test invariant is fragile. Either collapse
  to one counter or change the test to assert structural correlation.

- **W13 — rrtemporal test gaps.**
  - **Multi-Nexus-Link (>2 entries, header repetition):** handler-side
    encoding test exists (`nexus_handler_test.go:205-229`); response-side
    `extractNexusLinks` test only covers 2-entry case (line 598-621).
  - **`UNKNOWN` HandlerError wire value pass-through.**
  - **Concurrent in-flight cancel race:** `inFlight` `sync.Map` race
    between `Delete` (line 165) and `Load` (line 473) — defer order
    documented but no concurrent test.

## Positive findings (OK)

These confirm contracts that hold and should not be regressed.

- **Token format byte-equivalent across SDKs.** PHP encodes
  `base64url(JSON{"t":1,"ns":...,"wid":...})` with `JSON_UNESCAPED_SLASHES`
  and unpadded base64url — matches Go `URLEncoding.WithPadding(NoPadding)`
  and Java `OperationTokenUtil`. Golden vectors at
  `tests/Unit/Nexus/WorkflowRunOperationTokenTestCase` line 25-47 line up
  with Go. PHP's `v:0` acceptance also matches Go's `Version != 0` check
  (Go's error message about "v field should not be present" is misleading
  but its behavior agrees with PHP).
  File: `src/Nexus/Internal/WorkflowRunOperationToken.php:81-86`.

- **`WorkflowRunOperation::start()` is correctly always-async.**
  Returns `OperationInfo($token, OperationState::Running)` unconditionally
  (`src/Nexus/WorkflowRunOperation.php:98`). Matches Java
  `WorkflowRunOperationImpl` behavior. PHP doesn't expose `fetchResult` /
  `fetchInfo` (Java throws `UnsupportedOperationException`; PHP simply omits
  them — cleaner).

- **Sync ops correctly reject cancel** with
  `HandlerException(ErrorType::NotImplemented, ...)` before calling the
  inner handler. `src/Nexus/Handler/Internal/ServiceHandler.php:164-174`.

- **Service discovery walks `getInterfaces()`** correctly. Accepts
  `#[Service]` directly on the class OR walks interfaces (zero/multiple
  match rejected with helpful messages).
  `src/Internal/Declaration/Reader/NexusServiceReader.php:71-100`.

- **`LinkParser` is strict** — every malformed-input branch throws
  `HandlerException(ErrorType::BadRequest, ...)`. Spec-compliant; matches
  Go, beats Java (which returns null). All branches covered by
  `tests/Nexus/Unit/LinkParserTest.php`.

- **`#[ServiceImpl]` / `#[OperationImpl]` are GONE** from `src/`. Only
  remaining match is `Worker::registerNexusServiceImplementation()` (the
  public entry point, expected).

- **All three validators called from constructors that accept the value.**
  `NexusServicePrototype.php:46`, `NexusOperationPrototype.php:47`,
  `OperationCancelDetails.php:21`, `OperationInfo.php:28`. Defence in depth
  in `CancelNexusOperation.php:57-58`.

- **Wire field-by-field match** for `ExecuteNexusOperation`:
  `endpoint` / `service` / `operation` / `options` / `nexusHeaders` align
  between PHP DTO (`src/Internal/Transport/Request/ExecuteNexusOperation.php:46-57`)
  and Go struct (`roadrunner-temporal/internal/protocol.go:431-439`). The
  `nexusHeaders === [] ? new \stdClass() : ...` shim correctly forces a
  JSON object so Go's `map[string]string` decode succeeds.

- **`NexusStartEnvelope` decodes correctly.** PHP marshal names
  (`src/Internal/Workflow/NexusStartEnvelope.php:26-30`) match Go JSON tags
  (`aggregatedpool/nexus_caller.go:13-16`). `omitempty` on `Token` lets
  sync envelopes omit the field; PHP default `''` covers the absence.

- **`NexusOperationCancellationType` integer mapping is aligned 3-way.**
  PHP enum (`src/Workflow/NexusOperationCancellationType.php:20-36`),
  Go SDK (`sdk-go/internal/workflow.go:137-151` iota), RR comment
  (`internal/protocol.go:413` documenting `0=Unspecified, 1=Abandon, 2=TryCancel,
  3=WaitRequested, 4=WaitCompleted`).

- **`start()` exposed on stub interface.**
  `src/Workflow/NexusOperationStubInterface.php:53-58` returns
  `PromiseInterface<NexusOperationHandle>`. Equivalent in information to
  Go's `NexusOperationFuture` + `GetNexusOperationExecution()` two-future
  shape, but PHP collapses to one `start()` that resolves *after* started
  fires (slightly more conservative than Go).

- **Failure-conversion wire pass-through is consistent.** RR
  (`aggregatedpool/nexus.go:296-333`,
  `nexus_error_mapping_test.go:70-103`) passes the `HandlerErrorType` wire
  string through unchanged; sdk-go upstream owns the gRPC translation.
  The 11 spec wire values match PHP's `Exception/ErrorType.php:45-84`
  exactly.

## Cross-SDK parity matrix (post-review)

| Aspect | PHP | Go | Java | TS | Status |
|---|---|---|---|---|---|
| Token format | `base64url(JSON{t:1,ns,wid})` | same | same | same | ✅ aligned |
| Caller-side resolution | event-presence (started + completion) | event-presence | event-presence (state machine) | event-presence (two completion maps) | ✅ aligned |
| Sync vs async on handler | `#[AsyncOperation]` static attr | sealed-union per op | factory shape | value-shape return | ⚠ different surfaces, semantically aligned |
| Service registration | `#[Service]` interface + impl via `getInterfaces()` | values registered into `*nexus.Service` | factory annotations from `io.nexusrpc:nexus-sdk` | plain object via `nexus.serviceHandler` | ⚠ different surfaces |
| Async-cancel pairing | `#[OperationCancel(operation: 'name')]` separate method | inside `WorkflowRunOperation` | inside `OperationHandler` | inside `WorkflowRunOperationHandler` | ✅ semantically aligned |
| `WorkflowRunOperation` always-async | yes (no fetchResult/fetchInfo) | (Go's helper has both paths) | always-async (throws) | (TS's helper) | ✅ matches Java behavior |
| Link parsing strictness | strict (throws BadRequest) | strict | lenient (returns null) | strict | ✅ PHP correct, beats Java |
| Failure conversion (produce) | builders in `src/Nexus/Exception/` | `internal_nexus_task_handler.go:592-647` | `NexusTaskHandlerImpl.java:131-143, 234` | `packages/worker/src/nexus/` | 🔴 PHP missing gRPC-code table (E1) |
| Failure conversion (consume) | NOT IMPLEMENTED | implemented | implemented | implemented | 🔴 PHP gap (E2) |

## Test coverage observations

Aligned with `nexus_plan.md` P0–P5 priorities. New gaps surfaced by this
review:

- **F-coverage:** no `ErrorType::fromHttpStatus` / `::httpStatus` round-trip
  parameterized test (spec.md §5.4 lists 11 entries; nothing pins them).
- **F-coverage:** no `HandlerException::isRetryable()` default-table test.
- **F-coverage:** no consume-direction test for proto `HandlerError` →
  `HandlerException` reconstruction (because the production code doesn't
  exist — see E2).
- **F-coverage:** no cross-SDK golden-vector JSON test for the
  `HandlerError` wire shape next to `WorkflowRunOperationTokenTestCase::goldenVectors`.
- **W-coverage:** wire-shape JSON regression for `GetNexusOperationStarted`
  (W8).
- **R-coverage:** rrtemporal multi-link (>2), `UNKNOWN` HandlerError,
  concurrent cancel race (W13).

## Out-of-scope notes

- `samples-php/.../EchoCallerWorkflowImpl.php:39` references undefined
  `$options` inside `echo()`. Pre-existing bug in the samples repo, not
  this branch. File against `samples-php`.
- The previous completed PLAN.md (Nexus Branch Codebase Alignment Audit)
  closed all 12 tasks: layer violations, abbreviated variables, essay
  comments, missing `@internal`, `try/catch + self::fail()` anti-pattern,
  tautological tests. Those areas are clean in this review.

## Recommended fix order

1. **E1** — port the gRPC code → HandlerErrorType table into PHP.
2. **E2** — add consume-side reader (`handlerExceptionFromProto` +
   `OperationException::from`).
3. **W9** — `Discard(id)` in `Registry` to bound rrtemporal memory growth.
4. **W11** — strip `_rr_nexus_kind` defensively from sync-result payload
   metadata.
5. **W3** — pin `#[AsyncOperation]` ↔ `#[OperationCancel]` pairing at
   registration.
6. **W4** — capture-and-restore facade context on dispatch boundaries.
7. **W6** — update memory `project_nexus_caller_wire_design.md` to match
   the current `GetNexusOperationStarted` shape.
8. Test additions: F-coverage, R-coverage as listed above.
9. Lower-priority: W2, W5, W7, W8, W10, W12, W13 — schedule as separate PRs.

## Per-stream raw findings (preserved verbatim)

The four research streams' raw output is preserved here so the file is
self-contained for future re-reads.

### Stream 1 — Failure conversion + token

- F1 — Token v-field handling matches Go (re-classified to OK after
  re-reading Go source).
- F2 — `gRPC code → HandlerErrorType` table missing (→ E1).
- F3 — No consume-side reader for inbound HandlerError (→ E2).
- F4 — Recursive `cause` flattened to `_traceback` (→ W1).
- F5 — No non-retryable ApplicationError shortcut (→ W2).
- F6 — `WorkflowRunOperation::start()` always-async OK.
- F7 — Test gaps (HTTP round-trip, golden vector for HandlerError JSON).

### Stream 2 — Service registration + handler dispatch

- S1 — `#[AsyncOperation]` without `#[OperationCancel]` not rejected at
  registration (→ W3).
- S2 — Sync ops correctly reject cancel (OK).
- S3 — Facade static slot finally-clears, doesn't restore (→ W4).
- S4 — `MethodCanceller` cannot fire while sync handler runs (→ W5).
- S5 — Service discovery walks `getInterfaces()` correctly (OK).
- S6 — `LinkParser` is strict (OK, beats Java leniency).
- S7 — `#[ServiceImpl]` / `#[OperationImpl]` are gone (OK).
- S8 — Validators called from constructors (OK).

### Stream 3 — Caller-side wire

- W1 — Wire field shapes match end-to-end (OK).
- W2 — `NexusStartEnvelope` decodes correctly (OK).
- W3 — Memory note stale: code uses `GetNexusOperationStarted`, not
  `AwaitNexusOperationResult` (→ W6 in this review).
- W4 — Cancellation-type enum mapping aligned 3-way (OK).
- W5 — Cancel propagation correct (OK).
- W6 — `NexusServiceProxy` doesn't `implements` target interface (→ W7).
- W7 — `start()` exposed on stub; handle separates token from result (OK).
- W8 — Sample bug in `samples-php/.../EchoCallerWorkflowImpl.php:39` (out of scope).
- W9 — Tautological wire-shape test in `GetNexusOperationStartedTestCase`
  (→ W8).

### Stream 4 — roadrunner-temporal

- R1 — Registry has no `Discard`; orphan entries pile up (→ W9).
- R2 — Listen callbacks run under `c.Lock()` (→ W10).
- R3 — Pre-existing select+default pattern (informational, no action).
- R4 — Cancel-vs-completion ordering is safe (OK).
- R5 — `_rr_nexus_kind` not stripped from sync-result metadata (→ W11).
- R6 — gRPC code → HandlerErrorType wire pass-through consistent (OK).
- R7 — `seqID` / `invocationSeq` divergence is silent (→ W12).
- R8 — Test gaps (multi-link, UNKNOWN, concurrent cancel race) (→ W13).
