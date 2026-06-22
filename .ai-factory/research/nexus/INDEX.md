# Nexus research notes — index

Cross-SDK reference notes for the Temporal PHP SDK Nexus subsystem. Designed
to be re-loaded selectively when context is tight: each topic lives in its own
file with its own file:line refs into the relevant SDK source tree.

## When to read what

| If you need… | Open |
|---|---|
| Wire-level facts (endpoints, headers, OperationState, failure schemas, token format, callback POST) | `spec.md` |
| How Go does Nexus (caller futures, handler dispatch, state machine, failure-conversion table) | `sdk-go.md` |
| How Java does Nexus (state machine, factory-style impl, `io.nexusrpc:nexus-sdk` annotation library, `OperationTokenUtil`) | `sdk-java.md` |
| How TypeScript does Nexus (plain-object handler, value-shape async/sync, `NexusHandler` dispatch, `AsyncLocalStorage` context) | `sdk-typescript.md` |
| Cross-SDK usage patterns from sample apps (sync, async/WorkflowRun, multi-arg, context propagation, cancellation, errors, endpoint setup) | `samples.md` |
| RoadRunner Temporal Go-plugin (`rrtemporal`) — what's wired today, file:line refs into actual source | `roadrunner-temporal.md` |
| PHP gaps vs other SDKs and a concrete TODO list driven by samples | `cross-sdk.md` |

## File map (canonical paths in each SDK)

These are also embedded inside each notes file; reproduced here for quick
copy-paste:

- Spec / protocol SDK
  - Java: `/Users/xepozz/IdeaProjects/temporalio/nexus-rpc-sdk-java/nexus-sdk/`
  - PHP: `/Users/xepozz/IdeaProjects/temporalio/nexus-rpc-sdk-php/`
- sdk-go
  - Public: `/Users/xepozz/IdeaProjects/temporalio/sdk-go/temporalnexus/`
  - Internal: `/Users/xepozz/IdeaProjects/temporalio/sdk-go/internal/` (`workflow.go`, `internal_nexus_task_handler.go`, `internal_event_handlers.go`)
- sdk-java
  - Public: `/Users/xepozz/IdeaProjects/temporalio/sdk-java/temporal-sdk/src/main/java/io/temporal/nexus/`
  - Internal: `/Users/xepozz/IdeaProjects/temporalio/sdk-java/temporal-sdk/src/main/java/io/temporal/internal/nexus/`
  - State machine: `/Users/xepozz/IdeaProjects/temporalio/sdk-java/temporal-sdk/src/main/java/io/temporal/internal/statemachines/NexusOperationStateMachine.java`
- sdk-typescript
  - Public: `/Users/xepozz/IdeaProjects/temporalio/sdk-typescript/packages/nexus/`
  - Worker: `/Users/xepozz/IdeaProjects/temporalio/sdk-typescript/packages/worker/src/nexus/`
  - Workflow internals: `/Users/xepozz/IdeaProjects/temporalio/sdk-typescript/packages/workflow/src/internals.ts`
- Samples
  - Go: `/Users/xepozz/IdeaProjects/temporalio/samples-go/nexus*/`
  - Java: `/Users/xepozz/IdeaProjects/temporalio/samples-java/core/src/main/java/io/temporal/samples/nexus*/`
  - PHP: `/Users/xepozz/IdeaProjects/temporalio/samples-php/app/src/Nexus*` (branch `nexus`)
  - TS: `/Users/xepozz/IdeaProjects/temporalio/sdk-typescript/packages/test/src/test-nexus-*.ts`

## High-signal facts to remember without re-reading

- **Token format is byte-identical** across all SDKs: `base64url(JSON{t:1, ns, wid})`. Cancel re-derives workflow ID from the token; no separate registry.
- **Sync vs async on the caller side is event-presence based.** In all SDKs, the workflow-side decides based on whether `EVENT_TYPE_NEXUS_OPERATION_STARTED` arrives, not from a payload flag.
- **Sync vs async on the handler side is a sealed union on the wire** (`OperationStartResult`).
- **`WorkflowRunOperation` is always-async in Java**: no sync code path; relies on server-side callback. PHP should mirror.
- **Java's impl methods are factories** that return `OperationHandler` objects. Go and PHP put the operation logic directly on the impl method. Biggest cross-SDK divergence.
- **TypeScript encodes async vs sync by return value**, not via attribute. PHP's `#[AsyncOperation]` is static — different style; both work.
- **Failure-conversion table**: see `sdk-go/internal/internal_nexus_task_handler.go:592-647` and `sdk-java/temporal-sdk/.../NexusTaskHandlerImpl.java:131-143, 234`. Worth porting line-for-line.
- **PHP caller-side is wired in RoadRunner.** `aggregatedpool/handler.go:533` has `case *internal.ExecuteNexusOperation:`, plus the `GetNexusOperationStarted` arm at `:564`, plus a dedicated `aggregatedpool/nexus_caller.go` and `registry/nexus_started.go`. See `roadrunner-temporal.md`.
- **PHP samples live in `samples-php/` on the `nexus` branch.** Four scenarios (basic / cancellation / multi-arg / context-propagation) parity-aligned with Go and Java.

## Existing project docs (Russian)

These already exist and were used as starting context:

- `docs/nexus/spec.md` — protocol spec, conceptual.
- `docs/nexus/handler-side-sdk.md` — current PHP handler-side design.
- `docs/nexus/rr-integration.md` — PHP↔RoadRunner contract.
- `nexus_plan.md` — test-coverage gap matrix (PHP vs Java vs Go).

The notes in `.ai-factory/research/nexus/` complement these: they are organized
by SDK (not by topic), in English, and focus on file:line refs into the OTHER
SDKs so cross-checking is fast.
