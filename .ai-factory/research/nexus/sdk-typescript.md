# sdk-typescript Nexus — research notes

Reference notes for the TypeScript Temporal SDK's Nexus implementation,
captured for the PHP SDK developer. Written from a research subagent's
summary; some sections are intentionally short and link out to the canonical
files in `sdk-typescript/packages/` instead of duplicating their content.

> When in doubt, read the file. These notes are a navigation aid.

## A. Caller side (workflow → Nexus operation)

### Client construction

`createNexusClient` is the workflow-side entry point. It's typed against a
`ServiceDefinition` so per-operation input/output types are end-to-end —
`OperationInput<O>` and `OperationOutput<O>` are mapped types over the
service definition.

### Sync vs async resolution

The two-completion model lives at
`packages/workflow/src/internals.ts:681-730`:

- `resolveNexusOperationStart` — fires when the operation's start is acked
  (token available for async ops, completion-only for sync).
- `resolveNexusOperation` — fires when the result is available.

These map to two separate completion maps inside the workflow runtime, similar
to Go's dual-future shape but expressed as a single client API surface.

### Cancel propagation

Cancellation flows through `CancellationScope` → workflow internals; the
internals issue a cancel command and the result is then reported via
`resolveNexusOperation` with a cancellation reason.

### Failure mapping

`NexusOperationFailure` is the workflow-thrown type. Conversion from JSON
failure shapes happens on the worker side before completion is forwarded to
the workflow.

## B. Handler side

### Service shape — plain object

**No decorators.** `nexus.serviceHandler(service, impl)` takes:

1. The service definition (a value built from typed operation descriptors).
2. A plain-object impl whose keys must match operation names.

TypeScript's structural typing enforces that impl matches the service. PHP
uses `implements` covariance with `#[Service]` / `#[Operation]` attributes —
closer to Java than to TS.

### Sync vs async — value-shape encoded

**Async vs sync is decided by the return value**, not declared up-front:

- Return a value → sync.
- Return `nexus.HandlerStartOperationResult.async(token)` → async.

PHP's `#[AsyncOperation]` attribute is more static — declared at service
definition time, not at impl runtime. Both work; do not mix the models.

### WorkflowRunOperationHandler

`packages/nexus/src/workflow-helpers.ts:53-151` exposes `startWorkflow()` and
`WorkflowRunOperationHandler`. Closest analog to PHP `WorkflowRunOperation`.
Same wire output (token, link); same conceptual model: this op IS that
workflow.

### Context API — AsyncLocalStorage

Handler-side context (links, headers, deadline) is held in an
`AsyncLocalStorage` and accessed via helper functions. PHP's `Nexus::`
static accessor is the analog. TS naturally supports this without globals
because Node has built-in async-local-storage; PHP fakes it with explicit
context push/pop in the worker.

### Worker registration

`nexusServices` worker option — array of `nexus.serviceHandler(...)` results.
Worker registers each into its dispatch table at startup.

## C. Wire / runtime glue

### Rust core-bridge

The TS SDK runs on Rust core-bridge; Nexus tasks come in via `pollNexusTask`
and completions go out via `completeNexusTask` — separate from the Workflow
task channel. The dispatch class is `NexusHandler`:

`packages/worker/src/nexus/index.ts:31-252` — `NexusHandler` class, the
core-bridge ↔ user-handler glue. **This is the closest TS analog to PHP's
RoadRunner Nexus adapter.** When designing the PHP RR contract, scan this
file for the order of operations.

### Link converter

`packages/test/src/test-nexus-link-converter.ts` is the canonical reference
for the link wire shape. Same URL + HTTP `Link` header pattern as Go and
Java.

### Token helpers

`packages/test/src/test-nexus-token-helpers.ts` covers token format. Same
`base64url(JSON{t:1, ns, wid})` shape; **must be byte-identical** for
interop with Go/PHP/Java.

### Failure conversion

Failure → JSON conversion lives alongside the dispatch code in
`packages/worker/src/nexus/`. Same conceptual mapping as Java's table at
`NexusTaskHandlerImpl.java:131-143`.

### Interceptors

TS has Nexus-specific interceptors (caller and handler sides separate),
following the same pattern as the workflow interceptor chain.

## D. Files to bookmark

1. `packages/nexus/src/workflow-helpers.ts:53-151` — `startWorkflow()` +
   `WorkflowRunOperationHandler` (closest analog to PHP `WorkflowRunOperation`).
2. `packages/worker/src/nexus/index.ts:31-252` — `NexusHandler` dispatch
   class (RoadRunner adapter analog).
3. `packages/workflow/src/internals.ts:681-730` —
   `resolveNexusOperationStart` / `resolveNexusOperation` two-completion wire
   contract.
4. `packages/nexus/src/` — public Nexus API.
5. `packages/worker/src/nexus/` — worker integration.
6. `packages/test/src/test-nexus-handler.ts` — handler-side reference sample.
7. `packages/test/src/test-nexus-workflow-caller.ts` — caller-side reference
   sample.
8. `packages/test/src/test-workflow-nexus-cancellation.ts` — full cancellation
   matrix.
9. `packages/test/src/test-nexus-link-converter.ts` — link wire format.
10. `packages/test/src/test-nexus-token-helpers.ts` — token byte format.

## Top-3 file:line refs to bookmark

1. `packages/nexus/src/workflow-helpers.ts:53-151` — `startWorkflow` +
   `WorkflowRunOperationHandler`.
2. `packages/worker/src/nexus/index.ts:31-252` — `NexusHandler` dispatch.
3. `packages/workflow/src/internals.ts:681-730` —
   `resolveNexusOperationStart` / `resolveNexusOperation`.

## TS-specific patterns vs PHP

- **Service shape**: plain-object via `nexus.serviceHandler(service, impl)`.
  No decorators. Structural typing enforces match. PHP uses `implements`
  covariance + `#[Service]` / `#[Operation]` attributes (closer to Java).
- **Async vs sync**: **value-shape encoded** in TS — return
  `HandlerStartOperationResult.async(token)` for async. PHP's
  `#[AsyncOperation]` is static / attribute-level. Don't try to make PHP
  match TS here; PHP's static shape composes better with reflection-based
  registration.
- **Context**: TS uses `AsyncLocalStorage`. PHP uses `Nexus::` static
  accessors (push/pop in worker). Functionally equivalent.
- **Type-level service**: `NexusClient<T extends ServiceDefinition>` +
  mapped types `OperationInput<O>` / `OperationOutput<O>` give per-operation
  type safety end-to-end. PHP must lean on phpdoc generics + reflection at
  registration time. The runtime behaviour is the same; only the dev-time
  experience differs.
- **Token format must be byte-identical**: `base64url(JSON{t:1, ns, wid})`.
  All four SDKs agree.
