# sdk-go Nexus — research notes

Reference notes for the Go Temporal SDK's Nexus implementation, captured for the
PHP SDK developer. Written from a research subagent's summary; some sections are
intentionally short and link out to the canonical files in `sdk-go/` instead of
duplicating their content.

> When in doubt, read the file. These notes are a navigation aid, not a substitute.

## A. Caller side (workflow → Nexus operation)

### Client construction and operation invocation

Public surface lives under `sdk-go/workflow/`; the wiring lives in
`sdk-go/internal/`. The path of a Nexus call from a workflow:

1. `Workflow.NewNexusClient(endpoint, service)` → returns a `NexusClient`.
2. `NexusClient.ExecuteOperation(ctx, op, input, options)` → returns a
   `NexusOperationFuture`.
3. The future has a companion `GetNexusOperationExecution()` future that
   resolves once the *token* is known (independent of the result).

This dual-future shape is the most important Go vs PHP divergence — see the
"Caller-side patterns" section below.

Key file:line refs:

- `sdk-go/internal/workflow.go:3082-3158` — `ExecuteNexusOperation` + the dual-future wiring + cancel.
- `sdk-go/internal/internal_event_handlers.go:2027-2080` — `CancellationType` branch table; chooses which history event unblocks which future.

### Sync vs async resolution path

Sync vs async is **detected by event presence** on the caller side, not by the
schedule command shape:

- `NexusOperationStarted` → token is now available; second future resolves.
- `NexusOperationCompleted` → result available; first future resolves.
- For sync ops, `NexusOperationCompleted` arrives without `Started`; the SDK
  then resolves both futures from the single completion.

This is asymmetric with the handler side, where sync/async IS a oneof on the
wire. Do not symmetrize this in PHP unless you have a reason.

### Cancellation

Four `CancellationType` values control how cancel propagates and which history
event the futures unblock on. See `internal_event_handlers.go:2027-2080`.

Cancellation is **SDK-side**, not server-side: the workflow SDK decides when
to issue a `RequestCancelNexusOperation` command and when to consider the
operation cancelled.

### Failure mapping

`NexusOperationFailure` is the workflow-thrown exception type for failed Nexus
ops. Tight gRPC-status → `HandlerErrorType` table at
`sdk-go/internal/internal_nexus_task_handler.go:592-647` — worth porting line
for line into PHP if not already done.

## B. Handler side

### Service registration model

**Operations are VALUES, not interface methods.** This is the biggest shape
difference vs PHP/Java:

- Go: `nexus.NewSyncOperation(name, fn)` /
  `temporalnexus.NewWorkflowRunOperation(name, wf, getOpts)` registered into a
  `*nexus.Service`.
- PHP: `#[Service]`-annotated interface + impl class discovered via
  `getInterfaces()`.

The Go shape allows operations to be defined as standalone values, mixed and
matched into a service at registration time. PHP locks them to the interface
contract. Both work; do not "fix" PHP toward Go's shape.

### OperationHandler surface

`OperationHandler[I, O]` is the generic interface the handler dispatch
machinery sees. `Start` returns an `OperationStartResult[O]` (sealed-union:
sync result OR async token). `Cancel` takes the token and a context.

### WorkflowRunOperation helper

The "this op == that workflow" pattern lives in
`sdk-go/temporalnexus/operation.go:170-370` — `WorkflowRunOperation` Start +
Cancel + `ExecuteUntypedWorkflow`. **PHP's `WorkflowRunOperation::start` mirrors
this.** Verify the token format and link payload match byte-for-byte if you
ever change either side.

### Sync/async sealed union + failure conversion

`internal/internal_nexus_task_handler.go:168-348` is the heart of handler
dispatch — start handling, sync vs async response shaping, and failure
conversion all live here.

### Handler-side context

Links, headers, and deadline are surfaced via the handler `Context`. Concrete
types live alongside the operation handlers in `temporalnexus/`. There's also
an interceptor↔nexus middleware adapter (separate from the workflow
interceptor chain).

## C. Wire / runtime glue

### Worker integration

- `RegisterNexusService` on the worker registers a `*nexus.Service` value.
- Internally that builds a `nexus.NewServiceRegistry` keyed by service name
  and dispatches via the handler.

### Poller / dispatch

- `PollNexusTaskQueue` is the gRPC RPC pulled by the worker.
- Task handler dispatch lives in `internal_nexus_task_handler.go`.
- The workflow-side issues a `ScheduleNexusOperation` command; cancel issues
  `RequestCancelNexusOperation`.

### Link / token format

- **Token: base64url(JSON{t:1, ns, wid})** — opaque to caller, but Go cancel
  re-derives the workflow ID from the token without a separate registry. PHP
  can copy this verbatim.
- **Links: URL-shape + HTTP `Link` header**. Multi-link supported.

## D. End-to-end tests in sdk-go

- `sdk-go/test/integration_test.go` — integration tests including Nexus.
- `sdk-go/test/nexusclient/` — usage patterns / fixtures for end-to-end Nexus.
- Internal handler unit tests live next to `internal_nexus_task_handler.go`.

## Caller-side patterns Go does that PHP should be aware of

1. **Operations are values, not interface methods.** Different discovery
   shape; not a bug in either SDK.
2. **Two futures**, not one. `NexusOperationFuture` (result) +
   `GetNexusOperationExecution()` (token availability). PHP collapses to one
   `ExecuteNexusOperation` returning a sync/async union, then a separate
   `AwaitNexusOperationResult{id}`. Same information content, different
   surface — be careful when porting examples.
3. **Sync vs async on the caller side is detected by event presence**, not by
   the schedule command shape. The handler-side wire IS a oneof.
4. **Cancellation is SDK-side**, not server-side. Four `CancellationType`
   values control which history event resolves which future.
5. **Token is opaque base64url(JSON{t:1, ns, wid})**. Byte-identical across
   SDKs; cancel re-derives workflow ID from it.
6. **Failure conversion table** at `internal_nexus_task_handler.go:592-647` is
   worth porting line-for-line — the gRPC-status → HandlerErrorType mapping
   isn't fully spelled out in the spec.

## Top-3 file:line refs to bookmark

1. `sdk-go/internal/workflow.go:3082-3158` — caller-side dual-future wiring + cancel.
2. `sdk-go/internal/internal_nexus_task_handler.go:168-348` — handler start
   dispatch + sync/async sealed union + failure-to-wire mapping.
3. `sdk-go/temporalnexus/operation.go:170-370` — `WorkflowRunOperation`
   Start/Cancel + `ExecuteUntypedWorkflow` (the pattern PHP mirrors).

Bonus: `internal_nexus_task_handler.go:592-647` for the gRPC code →
HandlerErrorType failure-conversion table.
