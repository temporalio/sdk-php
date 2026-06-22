# Cross-SDK comparison and PHP gap matrix

Synthesis of the four SDK notes (`sdk-go.md`, `sdk-java.md`, `sdk-typescript.md`,
`samples.md`) plus the existing project docs (`docs/nexus/`, `nexus_plan.md`).

## Caller-side surface

| Aspect | Go | Java | TS | PHP (current) |
|---|---|---|---|---|
| Client construction | `Workflow.NewNexusClient(endpoint, service)` | `Workflow.newNexusServiceStub(...)` | `createNexusClient(...)` | `Workflow::newNexusServiceStub(...)` returns `NexusServiceProxy` |
| Result handle | `NexusOperationFuture` + `GetNexusOperationExecution()` (dual future) | single future via state machine | single client, two completion maps internally | `ExecuteNexusOperation` request → sync/async union, then `AwaitNexusOperationResult{id}` |
| Sync vs async detection (caller) | event-presence (`Started` event) | event-presence (`NexusOperationStateMachine.java:164`) | event-presence (two completion maps) | event-presence (matches others) |
| Cancel propagation | SDK-side, 4 `CancellationType` values | SDK-side via state machine | SDK-side via `CancellationScope` | not yet wired through to RR |
| Failure mapping (caller) | `NexusOperationFailure` + table at `internal_nexus_task_handler.go:592-647` | `NexusOperationFailure` + table at `NexusTaskHandlerImpl.java:131-143, 234` | `NexusOperationFailure` from worker | `OperationErrorFailure`/`HandlerErrorFailure` builders in `src/Nexus/Exception/` |

## Handler-side surface

| Aspect | Go | Java | TS | PHP |
|---|---|---|---|---|
| Service definition | values: `nexus.NewSyncOperation`, `temporalnexus.NewWorkflowRunOperation` registered into `*nexus.Service` | `@ServiceImpl`/`@OperationImpl` factories returning `OperationHandler<T,R>` (annotations from `io.nexusrpc:nexus-sdk`) | plain object: `nexus.serviceHandler(service, impl)` (structural typing) | `#[Service]` interface + impl class via `getInterfaces()` (closer to Java but no factory indirection) |
| Where logic lives | directly on the operation value | on a separate `OperationHandler` returned by the impl method (factory) | directly on impl method | directly on impl method |
| Sync vs async declaration | per-operation type (sealed union return) | factory shape determines | value-shape: return `HandlerStartOperationResult.async(...)` | static `#[AsyncOperation(output: ...)]` attribute |
| Async cancel pairing | inside `WorkflowRunOperation` itself | inside the `OperationHandler` object | inside `WorkflowRunOperationHandler` | paired `#[OperationCancel(operation: 'name')]` method on impl |
| Context accessors | per-context type via dispatch | `OperationContext`/`StartDetails`/`CancelDetails` | `AsyncLocalStorage` + helpers | `Nexus::` static accessors |

## Wire / runtime glue

| Aspect | Go | Java | TS | PHP |
|---|---|---|---|---|
| Worker dispatch | `internal_nexus_task_handler.go:168-348` | `NexusTaskHandlerImpl.java:78` | `NexusHandler` at `packages/worker/src/nexus/index.ts:31-252` | RoadRunner `rrtemporal` plugin (Go) routes `InvokeNexusOperation`/`CancelNexusOperation` to PHP |
| Token format | `base64url(JSON{t:1, ns, wid})` | same; `OperationTokenUtil.java:22` | same; `test-nexus-token-helpers.ts` | same; `WorkflowRunOperation::start` produces this |
| Link parsing | strict (throws) | **lenient** (returns null on malformed) | strict | strict (`HandlerException{BadRequest}`) |
| Failure-to-JSON | `internal_nexus_task_handler.go:592-647` | `NexusTaskHandlerImpl.java:131-143, 234` | `packages/worker/src/nexus/` | `OperationErrorFailure::from` / `HandlerErrorFailure::from` in `src/Nexus/Exception/` |

## PHP-specific gaps surfaced by the research

### Confirmed state (verified against actual sources)

- **Caller-side is wired in RoadRunner**:
  `roadrunner-temporal/aggregatedpool/handler.go:533` implements
  `case *internal.ExecuteNexusOperation:`, plus a dedicated
  `nexus_caller.go` and `registry/nexus_started.go`. See
  `roadrunner-temporal.md` for the full inventory.

- **PHP samples are in place**: `samples-php/app/src/` (branch `nexus`)
  ships `Nexus/`, `NexusCancellation/`, `NexusMultipleArguments/`,
  `NexusContextPropagation/` — each with its own caller worker, handler
  worker, and `ExecuteCommand.php`.

### Open items to verify

1. **`WorkflowRunOperation::start` always-async semantics** — verify the PHP
   handler-side path against the always-async semantics Java enforces
   (`fetchResult` / `fetchInfo` throw; server-side callback is the only
   result path). Compare to `samples-php/app/src/Nexus/Handler/`.

2. **`Workflow::newNexusServiceStub` proxy ergonomics** — verify against
   `samples-php/app/src/Nexus/Caller/EchoCallerWorkflowImpl.php` whether
   the proxy `implements` the target service interface or whether callers
   still need an `object` field workaround. If still a leak, decide: fix
   the proxy or document.

3. **Test-coverage gaps** — see `nexus_plan.md` P0–P5; not duplicated here.

### Test-coverage gaps (from `nexus_plan.md`, P0–P5)

The existing `nexus_plan.md` already lists these; reproducing one-line
summaries:

- **P0** — Caller-workflow failure-mapping. Verify all failure types map
  correctly through the caller-side proxy.
- **P1** — Async cancel matrix. Verify each `CancellationType`-equivalent
  path resolves the right way.
- **P2** — Timeouts (schedule-to-close, schedule-to-start, start-to-close).
- **P3** — Conflict policies (e2e).
- **P4** — Replay & parallelism.
- **P5** — Headers via interceptor + reverse links.

### Suggested ports / line-for-line copies

- **Failure-conversion table**: port from
  `sdk-go/internal/internal_nexus_task_handler.go:592-647` (gRPC code →
  HandlerErrorType). Already partially in PHP per `nexus_plan.md`; verify
  all rows match.
- **Token format**: byte-for-byte. Verify `WorkflowRunOperation::start`
  produces the exact same shape as Go and Java.
- **Link wire format**: HTTP `Link` header URL shape. Verify against
  `LinkParser`.

### Things PHP does *better* than Java

- **Strict link parsing.** Java returns `null` on malformed; PHP throws
  `HandlerException{BadRequest}`. Per spec, strict is correct.
- **Recursive `cause` modelling on `FailureInfo`** (per `spec.md` notes:
  `FailureInfo.php:30`). Java's `FailureInfo` does not model `cause`
  recursion.
