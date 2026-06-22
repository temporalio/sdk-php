# Serialization Context for Converters & Codecs

**Issue:** [temporalio/sdk-php#587](https://github.com/temporalio/sdk-php/issues/587) → cross-SDK [temporalio/features#434](https://github.com/temporalio/features/issues/434)
**Branch:** none (config `git.create_branches: false` — work on current branch)
**Created:** 2026-06-19

## Settings

- **Testing:** yes — unit + acceptance
- **Logging:** verbose (DEBUG when tracing context propagation)
- **Docs:** update `docs/data-conversion/` with a SerializationContext section
- **Roadmap linkage:** none (no ROADMAP.md configured)

## Problem

When (de)serializing a payload, a user's payload converter/codec may need to know the
payload's context — e.g. sign the payload with the workflow ID to defend against replay
attacks, or derive an encryption key from the namespace. Today PHP converters only ever
see a `Payload` + `Type`; they have no idea which workflow/activity/client call the
payload belongs to. Java, Go and TypeScript all expose a **serialization context** for
exactly this. PHP is the remaining open SDK ticket.

The context must reach converters on **both** the worker side (workflow/activity
input/output) **and** the client side (outbound calls + async activity completion) — it
is not just for codecs.

## Cross-SDK reference (research summary)

| SDK | Mechanism | Context shapes |
|-----|-----------|----------------|
| **Java** (`sdk-java#1695`) | `withContext(SerializationContext)` default method on `DataConverter`/`PayloadConverter`/`PayloadCodec`/`FailureConverter`, returns an immutable context-bound copy; default returns `this`. Delegating converters re-wrap each member per call. | marker `SerializationContext`; `HasWorkflowSerializationContext{namespace, workflowId?}`; `WorkflowSerializationContext{namespace, workflowId}`; `ActivitySerializationContext{namespace, workflowId?, workflowType?, activityType, activityTaskQueue, local}` |
| **Go** (`sdk-go#1352`) | Opt-in interfaces `DataConverterWithSerializationContext` / `PayloadCodecWithSerializationContext` / `FailureConverterWithSerializationContext` with `WithSerializationContext(ctx)`; free helpers type-assert and delegate, returning the original if not implemented. `CodecDataConverter` fans context into both parent DC and codecs. | sealed marker; `WorkflowSerializationContext{Namespace, WorkflowID}`; `ActivitySerializationContext{Namespace, WorkflowID, WorkflowType, ActivityType, TaskQueue, IsLocal}` |
| **TypeScript** (`sdk-typescript#1661`) | Optional trailing `context?: SerializationContext` parameter on every converter/codec/failure method; `undefined` = contextless. Worker stashes context by `seq` for resolve-side jobs. | discriminated union `{type:'workflow', namespace, workflowId}` \| `{type:'activity', namespace, activityId?, workflowId?, isLocal}` |

**Chosen design for PHP: Java/Go-style `withSerializationContext()`** returning an
immutable, context-bound converter, injected through the SDK's existing **late converter
injection** seam (`ValuesInterface::setDataConverter()`).

### Why this fits PHP

`EncodedValues`/`EncodedCollection` already hold a `DataConverterInterface` and call
`$converter->toPayload()/fromPayload()` (`src/DataConverter/EncodedValues.php:140,228`).
The converter is attached *late* via `setDataConverter()` at every wire boundary. If we
inject an **already context-bound** converter, `EncodedValues` needs **zero changes** and
no converter method signatures change — the context is "baked into" the injected
instance. The TS optional-parameter model would instead force signature churn through
`ValuesInterface`, `EncodedValues`, `EncodedCollection` and every converter, for no gain.

### BC refinement (important)

PHP interfaces cannot carry default methods, so adding `withSerializationContext()`
directly to the public `DataConverterInterface`/`PayloadConverterInterface` would
**break every third-party implementation**. We therefore follow **Go's opt-in interface**
model precisely: a new `SerializationContextAwareInterface`, implemented by the built-in
converters, plus a `bind()` helper that checks `instanceof` and returns the converter
unchanged when it is not context-aware. Custom user converters keep working untouched and
simply receive no context (same semantics as Go). A future major version could fold the
method into the base interfaces.

## PHP context shapes

```php
namespace Temporal\DataConverter;

interface SerializationContext {}

interface HasWorkflowSerializationContext extends SerializationContext {
    public function getNamespace(): string;
    public function getWorkflowId(): ?string;
}

final class WorkflowSerializationContext implements HasWorkflowSerializationContext {
    public function __construct(
        public readonly string $namespace,
        public readonly string $workflowId,
    ) {}
}

final class ActivitySerializationContext implements HasWorkflowSerializationContext {
    public function __construct(
        public readonly string $namespace,
        public readonly ?string $workflowId,   // null for standalone activities
        public readonly ?string $workflowType,
        public readonly string $activityType,
        public readonly string $taskQueue,
        public readonly bool $isLocal,
    ) {}
}
```

## Architecture map (verified call sites)

**Late-injection seam:** `ValuesInterface::setDataConverter()`
(`src/DataConverter/EncodedValues.php:163`, `EncodedCollection.php:183`). Decode/encode is
lazy, so re-binding before the first `getValue()`/`toPayloads()` is safe.

**Outbound (worker → RR) — Encoder overwrites the converter.**
`JsonCodec/Encoder.php:37,71` (and `ProtoCodec/Encoder.php`) unconditionally call
`$cmd->getPayloads()->setDataConverter($this->converter)`, discarding any upstream
binding. So outbound command DTOs must **carry** their `SerializationContext` (non-wire)
and the Encoder must apply `bind($this->converter, $cmd->getSerializationContext())`.

**Inbound server requests (RR → worker)** — `JsonCodec/Decoder.php:64,93` build
`EncodedValues` with the contextless converter; typed `WorkflowInfo`/`ActivityInfo` are
parsed *later* in the routers. Since decode is lazy, routers re-bind with context after
building the info.

**Resolve-side stash** — `src/Internal/Transport/Client.php:42,86` stores
`array<int, array{Deferred, WorkflowContextInterface|null}>` per request id, and
`dispatch()` (`:48-73`) resolves the deferred with the response `ValuesInterface`. This is
the single point that matches an outbound request to its response, so it is where the
recorded context is re-bound onto the response payloads (activity/child-workflow result
decoded with the same context used to encode the request).

## Tasks

> **Implementation note (revised during /aif-implement):** the serialization context is
> carried on the concrete `EncodedValues` (`setSerializationContext()`/`getSerializationContext()`)
> and bound to the converter *internally* at convert time via
> `SerializationContextBinder::bind()`. This removed the need for a command-carrier interface
> and any Encoder structural change (the Encoder still sets the base converter; `EncodedValues`
> layers the context on top). The binder lives in the public `DataConverter` namespace because
> Public→`Internal` is forbidden.

### Phase 1 — Core abstractions

- [x] **Task 1** — Add `SerializationContext` type hierarchy (`src/DataConverter/`):
  marker + `HasWorkflowSerializationContext` + `WorkflowSerializationContext` +
  `ActivitySerializationContext`. `readonly` props, named-arg construction, no comments.
- [x] **Task 2** — Add `SerializationContextAwareInterface` (`withSerializationContext(?SerializationContext): static`)
  + `bind()` helper (`$c instanceof … ? $c->withSerializationContext($ctx) : $c`, using an
  `if`-statement). DEBUG-log contextless converters. *(blocked by 1)*
- [x] **Task 3** — Make `DataConverter` + each `PayloadConverter` context-aware: immutable
  `withSerializationContext()` clone, re-wrapping each member converter per call; signatures
  of `toPayload`/`fromPayload` unchanged. *(blocked by 2)*

### Phase 2 — Transport plumbing

- [x] **Task 4** — Carry a non-wire `?SerializationContext` on outbound commands **without
  widening the public interfaces** (`RequestInterface`/`SuccessResponseInterface` are *not*
  `@internal` — adding methods would be a BC break). Add an opt-in
  `Worker\Transport\Command\SerializationContextCarrierInterface` implemented by the
  concrete `Request` base (+ a `setSerializationContext()` setter so DTO constructors stay
  untouched), `SuccessResponse`, and `UpdateResponse`. Apply it in `JsonCodec/Encoder.php`
  + `ProtoCodec/Encoder.php` via `bind()` for request payloads, success-response payloads,
  the **`UpdateResponse`** branch, and the `FailureConverter` calls. *(blocked by 3)*
- [x] **Task 5** — Re-bind response context in `Client::dispatch()` (resolve-side stash):
  record each request's context in the `$requests` registry, re-bind the response payloads'
  converter before `$deferred->resolve()`. *(blocked by 4)*

### Phase 3 — Client outbound

- [x] **Task 6** — Bind `WorkflowSerializationContext(namespace, workflowId)` at client seams:
  `src/Internal/Client/WorkflowStarter.php` (start/signalWithStart/updateWithStart) and
  `src/Internal/Client/WorkflowStub.php` *(path is `Internal/Client`, not `Client`)* —
  signal/query/update args **and** result decode (query result, `fetchResult`, update result
  via `ResponseToResultMapper`). *(blocked by 3)*

### Phase 4 — Worker side

- [x] **Task 7** — Worker activity: build `ActivitySerializationContext` from `ActivityInfo` in
  `InvokeActivity.php` (re-bind input after `unmarshal`, attach to result) and
  `ActivityContext.php` (heartbeat). `isLocal` has no carrier today — add an overridable
  `isLocal()` hook (`true` in `InvokeLocalActivity`, which currently extends `InvokeActivity`
  with no overrides; `ActivityInfo` has no `isLocal` field). *(blocked by 4, 5)*
- [x] **Task 8** — Worker workflow I/O + signal/query/update + **side-effect**: bind
  `WorkflowSerializationContext` in `StartWorkflow.php` (input rebind must sit **before
  `initAndStart` ~89**, which reads input synchronously), `WorkflowContext.php`
  (CompleteWorkflow/ContinueAsNew **and `SideEffect` output**), and the signal/query/update
  routers (InvokeUpdate rebind **before the synchronous validator ~67**; query/update
  results carried via SuccessResponse/UpdateResponse). **Explicitly do NOT bind
  header/memo/searchAttributes** (Java parity; memo/SA are raw `(object)` in `options` and
  never touch the PHP converter). *(blocked by 4, 5)*
- [x] **Task 9** — Workflow-issued commands: attach the correct context (via the carrier setter,
  not per-stub `setDataConverter` — the Encoder clobbers that) to
  `ExecuteActivity`/`ExecuteLocalActivity` (`ActivityStub.php` — needs a
  `Workflow::getCurrentContext()->getInfo()` hop for namespace/workflowId and
  `options->taskQueue ?? info->taskQueue` fallback), `ExecuteChildWorkflow`
  (`ChildWorkflowStub.php`, **child** workflow id — null when auto-generated, best-effort),
  `SignalExternalWorkflow` (`ExternalWorkflowStub.php`, **target** context — untyped stub
  hardcodes `namespace=''`, fall back to caller namespace). *(blocked by 4, 5)*

### Phase 5 — Failure, schedule, async completion

- [x] **Task 10** — Failure details (context-bound converter flows through `FailureConverter` /
  `TemporalFailure::setDataConverter` automatically once Task 4 lands); Schedule
  *(optional/secondary — Go-parity, weakest seam)* (`ScheduleMapper`/`ScheduleClient`/`ScheduleHandle`,
  workflow context from `StartWorkflowAction::$workflowId`); async `ActivityCompletionClient`
  (by-ID has workflowId/runId/activityId but **no** activityType/taskQueue → null; by-token
  has only the token → near-empty context, documented). *(blocked by 6, 7)*

### Phase 6 — Tests & verification

- [x] **Task 11** — Unit tests: context DTOs (real behaviour), `DataConverter::withSerializationContext`
  re-wrapping, `bind()` helper instanceof behaviour. *(blocked by 3)*
- [x] **Task 12** — Acceptance E2E mirroring Java `WorkflowIdSignedPayloadsTest` / Go
  `serialization_context_test`: a context-aware converter that signs with `workflowId` on
  encode and verifies on decode across client/workflow/activity(+local)/child. Generator +
  Fiber mirror per skill-context rules. *(blocked by 6, 7, 8, 9)*
- [x] **Task 13** — Full pyramid (`unit && arch && func && accept-fast && accept-slow`) + psalm +
  cs:fix; docs update. No `markTestSkipped`. (`tests/Arch` only checks debug functions — no
  namespace-dependency enforcement exists.) *(blocked by 10, 11, 12)*

## Commit Plan

- **Commit 1** (Tasks 1–3): `feat(data-converter): add SerializationContext types and context-aware converter API`
- **Commit 2** (Tasks 4–5): `feat(worker): carry serialization context through command transport`
- **Commit 3** (Task 6): `feat(client): pass workflow serialization context on outbound calls`
- **Commit 4** (Tasks 7–9): `feat(worker): bind serialization context for activities and workflows`
- **Commit 5** (Task 10): `feat: serialization context for failures, schedules and activity completion`
- **Commit 6** (Tasks 11–13): `test: serialization context unit + e2e coverage and docs`

## Risks & decisions

- **BC (converters)**: do not modify public `DataConverterInterface`/`PayloadConverterInterface` —
  use the opt-in `SerializationContextAwareInterface` + `bind()` (Go parity). Document this.
- **BC (commands)**: `RequestInterface`/`SuccessResponseInterface` are **not** `@internal`, so
  do not add methods to them either — use the opt-in `SerializationContextCarrierInterface`
  implemented by the concrete `Request`/`SuccessResponse`/`UpdateResponse` classes.
- **Encoder overwrite**: the Encoder unconditionally re-binds the converter, so any per-stub
  `setDataConverter` is clobbered. Outbound context must live on the command DTO and be
  applied at the Encoder (which only knows the command name, not typed workflow/activity info).
- **Resolve-side correctness**: activity/child results must decode with the request's
  context; the only safe seam is `Client::dispatch()` keyed by request id.
- **Non-wire**: `SerializationContext` is process-local — never serialize it into the wire
  `options` blob or any payload metadata.
- **Replay safety**: context is derived from `WorkflowInfo`/`ActivityInfo`/options that are
  already deterministic across replays — no new non-determinism is introduced.
- **Fiber/Generator**: runtime changes live in shared `src/Internal/Workflow/*`; both modes
  use the same path. The acceptance test must assert propagation in both, and verify no
  `Temporal\Experiments\Fibers\*` leak (skill-context rule). Run the **full** test pyramid.

## Known limitations (documented, out of scope)

- **Auto-generated child workflow id**: PHP assigns it server-side (unlike Go, which generates
  client-side), so `ExecuteChildWorkflow` input for an auto-id child gets a context with a null
  `workflowId`. A deterministic client-side id generator is a possible follow-up.
- **By-token activity completion**: only the opaque task token is known, so the
  `ActivitySerializationContext` is near-empty (no workflowId/activityId/type).
- **Untyped external workflow signal**: `ExternalWorkflowStub` carries `namespace=''`;
  context falls back to the caller's namespace.
- **Memo / search attributes / headers**: intentionally **not** contextualized (Java parity;
  memo/SA never pass through the PHP converter).
- **Nexus**: no `src/Nexus/` exists on this branch — no work here. When the handler-side Nexus
  serializer merges, it must opt out of workflow context (Java parity).

## Notes

- `tests/Arch/ArchTest.php` only enforces "no leftover debug functions" — there is **no**
  namespace-dependency arch test, and `DataConverter` already imports `Internal`/`Workflow`.
  Adding `SerializationContext` types there introduces no new violation.

## Next steps

```
/aif-implement

CONTEXT FROM /aif-plan:
- Plan file: .ai-factory/plans/serialization-context-converters-codecs.md
- Testing: yes (unit + acceptance)
- Logging: verbose
- Docs: yes
```
