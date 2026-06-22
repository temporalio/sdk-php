# Nexus samples — cross-SDK usage notes

Captured patterns from the four samples sources:

- Go: `samples-go/nexus/`, `nexus-cancelation/`, `nexus-context-propagation/`,
  `nexus-multiple-arguments/`
- Java: `samples-java/core/src/main/java/io/temporal/samples/nexus/`,
  `nexuscancellation/`, `nexusmultipleargs/`, `nexuscontextpropagation/`
- PHP: `samples-php/` on the `nexus` branch.
  `app/src/Nexus/`, `app/src/NexusCancellation/`, `app/src/NexusMultipleArguments/`,
  `app/src/NexusContextPropagation/`. Each directory ships its own
  `caller-worker.php` + `handler-worker.php` + `ExecuteCommand.php` + `README.md`.
  There's also `app/tests/Feature/Nexus/` with feature tests and a 37K
  `samples_plan.md` at the repo root.
- TypeScript: `sdk-typescript/packages/test/src/test-nexus-handler.ts`,
  `test-nexus-workflow-caller.ts`, `test-workflow-nexus-cancellation.ts` (TS
  uses tests as samples)

> When in doubt, open the sample. These notes are a navigation aid.

## 1. Basic sync operation

A service interface with one operation; impl returns a value directly; caller
workflow calls it and gets the result.

- Go: `samples-go/nexus/service/service.go` defines the operation as a value
  (`nexus.NewSyncOperation`).
- Java: service interface + `@OperationImpl` factory method returning a
  `SynchronousOperationHandler`-equivalent.
- PHP: `samples-php/app/src/Nexus/Service/SampleNexusService.php` (interface),
  `Handler/SampleNexusServiceImpl.php` (impl), three caller variants —
  `Caller/EchoCallerWorkflowImpl.php` (sync echo),
  `Caller/HelloCallerWorkflowImpl.php` (async via WorkflowRun),
  `Caller/HelloWithTokenCallerWorkflowImpl.php` (start/result split).
  `Service/EchoInput.php`, `EchoOutput.php`, `HelloInput.php`,
  `HelloOutput.php`, `Language.php` are the DTOs.
- TS: `test-nexus-handler.ts` uses `nexus.serviceHandler(service, impl)`
  plain object.

## 2. Async / WorkflowRun operation

An async op backed by a workflow.

- Go: `samples-go/nexus/service/service.go` uses
  `temporalnexus.NewWorkflowRunOperation(name, wf, getOpts)`. Caller has a
  separate token-availability future via `fut.GetNexusOperationExecution()`.
- Java: `WorkflowRunOperation` factory; **always-async**, no sync code path.
- PHP: `samples-php/app/src/Nexus/Handler/HelloHandlerWorkflowImpl.php`
  pairs with `WorkflowRunOperation::start(...)` inside
  `SampleNexusServiceImpl`. The start-vs-result split is shown in
  `Caller/HelloWithTokenCallerWorkflowImpl.php`.
- TS: `WorkflowRunOperationHandler` from
  `packages/nexus/src/workflow-helpers.ts`.

## 3. Multiple arguments

Nexus protocol takes ONE input. Each SDK packs into a DTO at the call site
and unpacks on the handler side.

- Go: `samples-go/nexus-multiple-arguments/` — DTO struct.
- Java: `samples-java/.../nexusmultipleargs/` — DTO class.
- PHP: `samples-php/app/src/NexusMultipleArguments/` —
  `Handler/SampleNexusServiceImpl.php` + `Handler/HelloHandlerWorkflowImpl.php`,
  caller in `caller-worker.php`. DTO uses Spiral Marshaller via
  `#[Marshal]`-style attributes.
- TS: structural type as input.

## 4. Context propagation

Headers flow caller workflow → Nexus call → handler → handler workflow.

- Go: `samples-go/nexus-context-propagation/` shows `ContextPropagator`
  interface usage.
- Java: `samples-java/.../nexuscontextpropagation/` uses Java's
  `ContextPropagator` API.
- PHP: `samples-php/app/src/NexusContextPropagation/` — full implementation:
  - `Propagation/MDC.php` — MDC-style propagator
  - `Propagation/NexusOutboundContextInterceptor.php` — caller-side
    interceptor that injects headers
  - `Caller/EchoCallerWorkflowImpl.php`, `Caller/HelloCallerWorkflowImpl.php`
  - `Handler/SampleNexusServiceImpl.php` — handler reads context out
- TS: covered in `test-nexus-workflow-caller.ts` via interceptors.

## 5. Cancellation paths

Caller workflow cancels → cancellation reaches the Nexus operation → handler
workflow is cancelled.

- Go: `samples-go/nexus-cancelation/` uses `CancellationType` to control
  whether the future resolves on requested-cancel or on completed-cancel.
- Java: `samples-java/.../nexuscancellation/` — propagates through
  `NexusOperationStateMachine`.
- PHP: `samples-php/app/src/NexusCancellation/` —
  `Caller/HelloCallerWorkflowImpl.php` and
  `Handler/HelloHandlerWorkflowImpl.php`. Plus
  `samples-php/app/tests/Feature/Nexus/` feature tests and the SDK's own
  tests (per `nexus_plan.md` P1 priority).
- TS: `test-workflow-nexus-cancellation.ts` covers the full matrix.

## 6. Error handling

- Go: handler errors → `nexus.HandlerError` with a typed code; app errors →
  `nexus.OperationError` with state `failed`/`canceled`.
- Java: same split; conversion table at
  `NexusTaskHandlerImpl.java:131-143`.
- PHP: `OperationErrorFailure::from()` + `HandlerErrorFailure::from()` are
  the canonical builders (`src/Nexus/Exception/`).
- TS: same shape; `HandlerStartOperationResult.async`/`.sync`/`.error`.

## 7. Worker setup / endpoint registration

The endpoint maps a name to a target task queue. Both caller and handler
workers must agree on the endpoint name; the handler worker must poll the
target task queue.

- Endpoint creation is **out-of-band** via `temporal nexus endpoint create`
  (CLI) or programmatic via Operator service.
- PHP samples ship `caller-worker.php` and `handler-worker.php` per
  scenario; `app/tests/Feature/Nexus/NexusEndpointHelper.php` is the
  programmatic helper used by tests.

## Residual gaps to verify

1. **Caller stub typing leak.** Whether `Workflow::newNexusServiceStub`
   returns a proxy that implements the target interface is worth verifying
   against the canonical samples in `samples-php/app/src/Nexus/Caller/`.
   If the proxy still doesn't `implements` the interface, decide whether to
   fix the proxy or document.
2. **Compare PHP samples to Go/Java line-by-line for parity drift.**
   Especially for cancellation type mapping and context-propagation header
   names.
3. **Read `samples-php/samples_plan.md` (37K)** — it is the canonical
   plan-of-record for the PHP samples and likely already enumerates open
   tasks.
