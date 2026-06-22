# sdk-java Nexus — research notes

Reference notes for the Java Temporal SDK's Nexus implementation, captured for
the PHP SDK developer. Written from a research subagent's summary; some
sections are intentionally short and link out to the canonical files in
`sdk-java/temporal-sdk/src/main/java/io/temporal/` instead of duplicating their
content.

> When in doubt, read the file. These notes are a navigation aid, not a substitute.

## A. Caller side (workflow → Nexus operation)

### Stub creation and operation invocation

Workflows obtain a Nexus service stub via `Workflow.newNexusServiceStub` (or
similar) — addressing is by endpoint + service. Options are configured via
`NexusOperationOptions`. `executeNexusOperation` returns a future-like handle
that resolves with the operation result.

### Sync vs async resolution

The state machine at
`temporal-sdk/src/main/java/io/temporal/internal/statemachines/NexusOperationStateMachine.java:164`
shows: **sync vs async is decided based on whether `EVENT_TYPE_NEXUS_OPERATION_STARTED`
arrives**, not on a payload flag. This matches Go's behaviour exactly — the
caller-side wire only carries one event for a sync op (completion) and two for
async (started + completed/failed).

### Cancel propagation

Cancel from the caller workflow flows through the state machine in the same
file; eventually a `RequestCancelNexusOperation` command is emitted.

### Failure mapping

`NexusOperationFailure` is the workflow-thrown exception type, mirroring Go's
naming. The exception → `HandlerError` mapping table lives at
`NexusTaskHandlerImpl.java:131-143` and `:234`.

## B. Handler side

### Cross-SDK annotation library

**`@ServiceImpl` and `@OperationImpl` come from `io.nexusrpc:nexus-sdk`**, not
from Temporal Java. This is the same model PHP follows with the
`nexus-rpc/sdk-php` path repo dependency. The Temporal Java SDK only provides
the *bridge* between the Nexus protocol SDK and the Temporal worker.

This split is important: the protocol SDK is portable and Temporal-agnostic;
the Temporal SDK glues it onto the worker poller and handler dispatch.

### Impl methods are factories

**Java's biggest divergence from Go and PHP**: in Java, an impl method is a
**factory** that returns an `OperationHandler<T, R>` object. The actual
`start` and `cancel` logic lives on a separate handler object.

Go and PHP both put the operation logic directly on the impl method (closer to
the Activity model). When porting examples between Java and PHP, expect to
collapse one level of indirection.

### WorkflowRunOperation helper

`WorkflowRunOperationImpl.start` is **always-async**, even when the underlying
workflow could resolve synchronously. `fetchResult` and `fetchInfo` throw
`UnsupportedOperationException` because Temporal uses the **server-side
callback path** instead of Nexus polling.

This matters when porting samples: Java's helper does NOT have a sync code
path. PHP's `WorkflowRunOperation::start` should mirror the always-async
behaviour to preserve interop semantics.

### Context accessors

`OperationContext`, `OperationStartDetails`, `OperationCancelDetails` exist in
the protocol SDK and are surfaced through the impl methods. PHP's
`Nexus::getCurrentContext()` / `getStartDetails()` / `getCancelDetails()` are
direct analogues.

### Worker registration

Services are registered with the Worker via the standard
`Worker.registerNexusServiceImplementation(...)`-style API (exact name to
verify in source). The Worker holds a registry keyed by service name and
dispatches Nexus tasks to the right impl.

## C. Wire / runtime glue

### Central handler dispatcher

`temporal-sdk/src/main/java/io/temporal/internal/nexus/NexusTaskHandlerImpl.java:78`
is the heart of handler dispatch — Start/Cancel switch, request-timeout
cancellation, exception → `HandlerError` mapping.

Failure-conversion table:

- Lines `131-143` — exception type → `HandlerError`.
- Line `234` — gRPC code → handler-error type.

Worth porting line-for-line into PHP's
`src/Internal/Nexus/` failure-mapping code.

### Operation token

`temporal-sdk/src/main/java/io/temporal/internal/nexus/OperationTokenUtil.java:22`
plus `WorkflowRunOperationToken.java` define the wire format:

```
base64url(JSON{"t":1, "ns":..., "wid":..., "v":opt})
```

**Must match byte-for-byte for PHP↔Java cancel interop.** Same shape as Go.
The `v` field is optional; current Java emits `t:1` without `v`.

### Link parsing

Java is **lenient on malformed links**: `LinkConverter.nexusLinkToWorkflowEvent`
returns `null` rather than throwing. Go and PHP throw `BAD_REQUEST` on
malformed input. PHP's stricter behaviour is correct per the spec — flag this
if you ever need to interop test against Java handlers that emit malformed
links.

### Interceptor stack

Java has a Nexus-specific interceptor chain layered on top of the worker
interceptor chain. Outbound (workflow caller) and inbound (handler) sides are
separate.

### Payload serializer

The same data converter used for workflows and activities also serializes
Nexus payloads, with the standard Proto/JSON/Binary/Null chain.

## D. Files to bookmark

1. `temporal-sdk/src/main/java/io/temporal/internal/nexus/NexusTaskHandlerImpl.java` — handler dispatch + failure conversion.
2. `temporal-sdk/src/main/java/io/temporal/internal/statemachines/NexusOperationStateMachine.java` — caller-side sync/async detection.
3. `temporal-sdk/src/main/java/io/temporal/internal/nexus/OperationTokenUtil.java` + `WorkflowRunOperationToken.java` — token format.
4. `temporal-sdk/src/main/java/io/temporal/nexus/` — public API entry points.
5. `temporal-sdk/src/main/java/io/temporal/internal/nexus/` — internal machinery.
6. `temporal-sdk/src/test/java/io/temporal/internal/nexus/` — internal tests.
7. `temporal-sdk/src/test/java/io/temporal/workflow/nexus/` — workflow-level (caller) tests.
8. `temporal-serviceclient/src/main/proto/temporal/api/nexus/` — proto definitions.

## Top-3 file:line refs to bookmark

1. `temporal-sdk/src/main/java/io/temporal/internal/nexus/NexusTaskHandlerImpl.java:78` —
   central handler dispatcher (Start/Cancel switch, request-timeout
   cancellation, exception → HandlerError mapping at 131-143, gRPC →
   handler-error table at 234).
2. `temporal-sdk/src/main/java/io/temporal/internal/statemachines/NexusOperationStateMachine.java:164` —
   sync-vs-async detection: workflow side decides based on whether
   `EVENT_TYPE_NEXUS_OPERATION_STARTED` arrives, not on a payload flag.
3. `temporal-sdk/src/main/java/io/temporal/internal/nexus/OperationTokenUtil.java:22` +
   `WorkflowRunOperationToken.java` — token wire format
   `base64url(JSON{"t":1, "ns":..., "wid":..., "v":opt})`. Must match
   byte-for-byte for PHP↔Java cancel interop.

## Java vs Go vs PHP — patterns to flag

- **Annotations source**: Java uses `io.nexusrpc:nexus-sdk` annotations;
  Temporal Java only bridges to the Worker. PHP follows the same model with
  `nexus-rpc/sdk-php`. Go does NOT use annotations — operations are
  registered as values.
- **Impl method indirection**: Java's impl methods are factories returning
  `OperationHandler` objects. Go and PHP put logic directly on the impl.
  Biggest divergence.
- **WorkflowRunOperation always-async**: Java explicitly does NOT support a
  sync path in `WorkflowRunOperationImpl`; it relies on server-side callback.
  PHP should mirror.
- **Link leniency**: Java returns null on malformed; Go and PHP throw. PHP is
  correct per spec.
