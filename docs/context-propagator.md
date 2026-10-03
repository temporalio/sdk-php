# ContextPropagator (design draft)

> **Status: not implemented.** This document tracks the gap between sdk-php
> and sdk-java/sdk-go and proposes the contract before code lands.

## Why we need it

Temporal SDKs in Java and Go ship a `ContextPropagator` mechanism: a pluggable
hook that captures values from the caller's runtime (typically `MDC`,
OpenTelemetry baggage, request-scoped IDs) and re-attaches them on the worker
side before user code runs. The wire shape is a `Map<String, Payload>` carried
under the workflow's `header` field (the Temporal interceptor `Header`,
distinct from Nexus operation headers).

Without it, sdk-php callers cannot propagate request-scoped diagnostic context
across workflow boundaries — every cross-service call has to thread the
context through application code by hand.

The Nexus context-propagation sample
([`samples-php/app/src/NexusContextPropagation`](https://github.com/temporalio/samples-php/tree/master/app/src/NexusContextPropagation))
works around the gap by logging at the Nexus service-impl boundary instead of
inside the started handler workflow body — this design lifts that limitation.

## Java/Go reference shape

```java
interface ContextPropagator {
    String getName();
    Object getCurrentContext();
    void setCurrentContext(Object context);
    Map<String, Payload> serializeContext(Object context);
    Object deserializeContext(Map<String, Payload> context);
}
```

Registration:

- `WorkflowClientOptions.setContextPropagators(...)` — applied on outbound RPC
  (workflow start, signal, query, update). Captures and serialises the current
  context into the request `Header`.
- `WorkerFactoryOptions.setContextPropagators(...)` — applied inside the
  worker. On every workflow / activity / nexus task pickup, the worker
  deserialises the `Header` payloads back into propagator-specific objects and
  calls `setCurrentContext()` so that user code in that task sees the values.

## Proposed PHP shape

### 1. Interface

```php
namespace Temporal\Common\ContextPropagator;

use Temporal\Api\Common\V1\Payload;

interface ContextPropagatorInterface
{
    /**
     * Stable identifier — collisions across propagators are an error;
     * the registry uses this to dedupe.
     */
    public function getName(): string;

    /**
     * Snapshot whatever the propagator considers "current context". The
     * return type is opaque (mixed) so each propagator can use whatever
     * shape it wants — array<string,string> for MDC-style, a value object,
     * etc.
     */
    public function getCurrentContext(): mixed;

    /**
     * Restore a context object captured earlier by `getCurrentContext()` /
     * deserialised by `deserializeContext()`. Called on the worker side
     * before each workflow / activity / nexus task body runs.
     */
    public function setCurrentContext(mixed $context): void;

    /**
     * Serialise `$context` to a payload map keyed by string. The map gets
     * merged into the request `Header` on the wire.
     *
     * @return array<string, Payload>
     */
    public function serializeContext(mixed $context): array;

    /**
     * Inverse of `serializeContext`. Receives the slice of the request
     * `Header` whose keys this propagator owns (the registry filters by
     * `getName()` prefix).
     *
     * @param array<string, Payload> $context
     */
    public function deserializeContext(array $context): mixed;
}
```

### 2. Client-side registration

Extend `Temporal\Client\ClientOptions`:

```php
public array $contextPropagators = [];

public function withContextPropagators(ContextPropagatorInterface ...$ps): self
{
    $self = clone $this;
    $self->contextPropagators = $ps;
    return $self;
}
```

`WorkflowClient::create()` already takes `ClientOptions`, so the registry
flows in without API changes.

### 3. Worker-side registration

Extend `Temporal\Worker\WorkerOptions`:

```php
public array $contextPropagators = [];

public function withContextPropagators(ContextPropagatorInterface ...$ps): self
{
    $self = clone $this;
    $self->contextPropagators = $ps;
    return $self;
}
```

`WorkerFactory::newWorker(taskQueue, options: ...)` already accepts
`WorkerOptions`. Default — empty array — keeps current behaviour.

### 4. Outbound integration (client)

Two boundaries care about it:

| Outbound surface | Where to hook | What to do |
|---|---|---|
| `StartWorkflowExecutionRequest` | `Internal/Client/WorkflowStarter::executeRequest()` | call `serializeContext()` on each propagator, merge into `request.header.fields` |
| `SignalWorkflowExecutionRequest`, `QueryWorkflowRequest`, `UpdateWorkflowExecutionRequest` | corresponding methods on `WorkflowStub` | same |
| `Workflow::executeNexusOperation()` (caller workflow → server) | already passes a `HeaderInterface` through `ExecuteNexusOperation` request | propagator output merged into it |

The merge rule: propagator payloads under their own keys; nothing collides
with existing user headers. Order — propagator iteration order, last-write
wins on key collision.

### 5. Inbound integration (worker)

Three task types need restoration:

- **Workflow task**: in `Internal/Workflow/WorkflowDefinition::initialize()` (or
  whichever runs before the workflow method body), iterate propagators and
  call `setCurrentContext(deserializeContext(slice_of_header))`.
- **Activity task**: in `ActivityWorker` dispatcher, same.
- **Nexus operation task**: trickier — see next section.

Cleanup is implicit because each PHP worker process handles tasks
sequentially; the next `setCurrentContext` call overwrites stale state. For
robustness we should still call `setCurrentContext(null)` (or a sentinel) at
task end so a static/global-backed propagator doesn't leak between tasks.

### 6. Nexus-specific wrinkle

Nexus operations carry **two header maps** simultaneously:

- `commonpb.Header` — Temporal interceptor header, payload-typed values. Same
  thing workflows and activities use. ContextPropagator hooks into this.
- `nexus.Header` — raw HTTP-style strings (`x-nexus-*`). Distinct concept,
  travels on the Nexus wire to the handler's `OperationContext::$headers`.

Java's `MDCContextPropagator` only deals with the **first**. The
`NexusMDCContextInterceptor` is a separate piece that reads MDC at outbound
time and writes the same value to the **second** map for handlers that don't
share the propagator.

For PHP we mirror the split:

- `ContextPropagatorInterface` handles the workflow-style `Header` end-to-end.
  This means caller workflow → started handler workflow body restoration
  Just Works once both ends register the same propagator.
- The Nexus operation headers (raw strings) stay a manual interceptor concern
  — the existing `WorkflowOutboundCallsInterceptor::executeNexusOperation()` +
  `OperationContext::$headers` paths are sufficient. No SDK change there.

So with ContextPropagator landed, the
[NexusContextPropagation sample](https://github.com/temporalio/samples-php/tree/master/app/src/NexusContextPropagation)
can be simplified:

```php
// Caller workflow body
$propagator->getCurrentContext()['x-caller-workflow-id'] = $workflowId;
yield $sampleNexusService->hello($input);  // ContextPropagator captures
// the workflow header automatically, no need for the explicit Nexus
// outbound interceptor for this case.

// Handler workflow body
$id = $propagator->getCurrentContext()['x-caller-workflow-id'];
// Available because the propagator was registered on the handler worker
// and ran before the workflow method.
```

### 7. Wire format

Already stable — it's `commonpb.Header`. PHP marshalling of `Header` already
handles `Map<String, Payload>` round-trip via `EncodedCollection`. The
outbound piece just needs to call propagators before the `Header` is
finalised; the inbound piece already gets the deserialised header on
`WorkflowInfo` / `ActivityInfo`.

### 8. RR boundary

No changes required: RR already forwards `commonpb.Header` between PHP and Go
both ways. The Go SDK on the other side does **not** need any of this — the
PHP worker is what runs `setCurrentContext()` for PHP user code.

### 9. Test surface

- Unit: each propagator's `serialize/deserialize` round-trips an arbitrary
  context value through a fake `Header`.
- Integration (functional): start a workflow with a registered propagator on
  the client, assert the workflow body sees the restored context. Repeat for
  signal / query / update.
- Acceptance: one Nexus end-to-end test verifying a value set on the caller
  worker shows up inside the handler workflow body without any Nexus header
  plumbing.

## Implementation phases

1. **Phase A — interface + registry**: add `ContextPropagatorInterface`, plumb
   through `ClientOptions` and `WorkerOptions`. No behaviour change yet.
2. **Phase B — outbound on workflow start**: serialise into start-request
   header; functional test for the start path.
3. **Phase C — inbound on workflow task**: deserialise + restore. Functional
   test for round-trip.
4. **Phase D — signal/query/update + activities**: same plumbing on the
   smaller surfaces.
5. **Phase E — Nexus integration**: ensure propagator output rides on the
   ExecuteNexusOperation `Header`; verify handler workflow body sees the
   restored context. Update the
   [NexusContextPropagation sample](https://github.com/temporalio/samples-php/tree/master/app/src/NexusContextPropagation)
   to use the propagator instead of the static `MDC` workaround.

Each phase is independently mergeable and each carries its own test layer.
