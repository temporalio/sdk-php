# Plan (PHP side): Collapse to single canonical handler path; replace `_rr_nexus_*` markers with typed reply command

**Branch:** nexus (no new branch — `git.create_branches: false`)
**Created:** 2026-05-07
**Owner:** PHP SDK team
**Mode:** full

> **Companion plan (Go side):**
> `temporalio/roadrunner-temporal` →
> `.ai-factory/plans/nexus-proto-wire-go.md`.
> Both plans implement the same wire contract (Section "Wire Contract"
> below is identical in both files). One coordination point: this PHP
> plan emits `Message.Command = "NexusOperationStarted"` reply; the Go
> plan adds the matching reply-decoder DTO. Land in the same deploy
> window.

## Settings

- **Testing:** yes (PHPUnit suite under `tests/Unit/Nexus/`)
- **Logging:** match the existing `Internal/Nexus/` style — no new
  `error_log` / PSR logger calls.
- **Docs:** yes (this repo's `docs/nexus/`)

## Roadmap Linkage

Milestone: "none"
Rationale: Skipped — no roadmap artifact in the repository.

## Motivation

**Two structural problems, addressed together:**

1. **Metadata-marker hack on the wire** —
   `Internal/Nexus/RoadRunner/Metadata.php` constants
   `_rr_nexus_kind` / `_rr_nexus_async` / `_rr_nexus_links` are stuffed
   into `commonpb.Payload.Metadata` by
   `NexusTaskHandler::startOperationDirect()` to encode async vs sync
   and to ship handler links. Once Nexus releases, these private keys
   become an unwritten wire contract.

2. **Two parallel handler paths** —
   `NexusTaskHandler.php` has both:
   - **`handleStartOperation(Request): Response`** /
     `handleCancelOperation(Request): Response` — canonical, uses
     `Temporal\Api\Nexus\V1\Request` / `Response` end-to-end, mirrors
     sdk-go (`internal/internal_nexus_task_handler.go:143-340`),
     sdk-java
     (`temporal-sdk/.../internal/nexus/NexusTaskHandlerImpl.java:291-356`)
     and sdk-typescript
     (`packages/worker/src/nexus/index.ts:99-152`) conceptually.
   - **`startOperationDirect(...)`** /
     `cancelOperationDirect(...)` — RR-bridge that writes the metadata
     markers above. Has **no analog in any sibling SDK.**

The two-path design exists only because the RR wire couldn't carry
`nexuspb.Response` natively, so RR-specific markers were stuffed into
payload metadata as a stop-gap. Every future Nexus feature
(`OperationInfo`, additional `StartOperationResponse` fields, etc.)
would have to be implemented twice and stay in sync. Sibling SDKs
prove a single canonical path is sufficient.

**Replacement.** Collapse to one path: the routes
(`Router/InvokeNexusOperation.php`,
`Router/CancelNexusOperation.php`) construct
`Temporal\Api\Nexus\V1\Request` from the existing JSON `options` +
`payloads`, call the canonical
`NexusTaskHandler::handleStartOperation(Request) /
::handleCancelOperation(Request)`, and translate the returned
`Response` into a typed reply command on the wire. The
`*Direct` methods are deleted.

**Out of scope:**

- `roadrunner-server/api` proto schema. **Untouched.**
- `roadrunner-php/roadrunner-api-dto`. **No version bump.**
- `Router/InvokeNexusOperation.php` / `Router/CancelNexusOperation.php`
  / `Router/CancelNexusOperationMethod.php` are **not renamed**. The
  Go-side companion plan keeps `InvokeNexusOperation` /
  `CancelNexusOperation` as command names (codebase convention is
  `Invoke<HandlerKind>` for all PHP user-handler dispatches: 6 of 6
  existing commands).
- `LinkParser::fromRaw` / `::fromProto` / `::fromHeader`,
  `NexusFailureConverter`, `NexusLinkConverter`,
  `NexusInvocationRegistry`, `MethodCanceller` — unchanged.
- `Router/CancelNexusOperationMethod.php` — RR cooperative cancel,
  no Nexus analog. Untouched.

## Scope

`src/Internal/Transport/Router/InvokeNexusOperation.php` (rewritten),
`src/Internal/Transport/Router/CancelNexusOperation.php` (rewritten),
`src/Internal/Nexus/NexusTaskHandler.php` (delete `*Direct` methods +
private helpers; extend `handleStartOperation` signature with an
optional `?MethodCanceller`),
`src/Internal/Nexus/RoadRunner/Metadata.php` (deleted),
`src/Worker/Transport/Command/Client/NexusOperationStarted.php` (new
outbound type),
`src/Worker/Transport/Codec/ProtoCodec/Encoder.php` (one new
`instanceof NexusOperationStarted` branch),
`tests/Unit/Nexus/IntegrationTestCase.php` (rewrites of
metadata-marker assertions),
`tests/Unit/Internal/Transport/Router/` (new route-level tests,
mirrors source path; existing precedent:
`CancelNexusOperationMethodRouteTestCase.php`),
`docs/nexus/`.

## Wire Contract

> **This section is the immutable spec shared with the Go-side plan.**
> Both teams implement against it. Any change here requires updating
> both plans before either side ships.

### Request side (Go → PHP) — unchanged

PHP receives requests in the existing JSON-options shape:

| RR command name              | `getOptions()` JSON                                                                              | `getPayloads()`         |
|------------------------------|--------------------------------------------------------------------------------------------------|--------------------------|
| `InvokeNexusOperation`       | `{service, operation, requestId, callback, callbackHeaders, headers, links, invocationId}`       | `[input_payload]` (when present) |
| `CancelNexusOperation`       | `{service, operation, operationToken}`                                                            | none                     |
| `CancelNexusOperationMethod` | unchanged (RR cooperative method-cancel; not Nexus)                                              | n/a                      |

Routes match by command name (existing `Route::getName()` derives from
short class name). No renames.

### Response side (PHP → Go) — one typed reply command

PHP success on `InvokeNexusOperation` sets `Message.command =
"NexusOperationStarted"` on the response. Past-tense name follows the
codebase's only existing reply-command precedent (`UpdateCompleted`,
`UpdateValidated` in
`src/Internal/Transport/Command/...UpdateResponse.php`).

The reply DTO shape **extends** the caller-side `NexusStartEnvelope`
(`src/Internal/Workflow/NexusStartEnvelope.php`, Go side at
`aggregatedpool/nexus_caller.go:13`) with an optional `links` field.
Caller-side envelope today carries only `{async, token}`; the
handler-side reply adds `{links: array<{url,type}>?}` to thread
handler-emitted Nexus links onto the wire without payload-metadata
markers. The Go-side companion plan must add `Links []NexusLink` on its
`internal.NexusOperationStarted` decoder DTO; this is the single
cross-team coordination point.

| PHP outcome of `InvokeNexusOperation` | Reply `command`         | Reply `options` JSON              | Reply `payloads`     | Reply `failure`                                                                                                  |
|---|---|---|---|---|
| Sync success (`SyncOperationStartResult`)        | `NexusOperationStarted` | `{async: false, links?}`          | `[result_payload]`   | none                                                                                                              |
| Async success (`AsyncOperationStartResult`)      | `NexusOperationStarted` | `{async: true, token, links?}`    | none                 | none                                                                                                              |
| `OperationException` (Failed/Canceled)           | (none)                  | n/a                               | n/a                  | `failurepb.Failure` from `NexusFailureConverter::operationExceptionToProto` (existing — produces the `nexus.OperationError.{Failed\|Canceled}` `ApplicationFailureInfo.Type` prefix consumed by Go-side `nexusErrorFromFailure`) |
| Generic `\Throwable` / `HandlerException`        | (none)                  | n/a                               | n/a                  | `failurepb.Failure` from `NexusFailureConverter::handlerExceptionToProto` (existing)                              |

| PHP outcome of `CancelNexusOperation` | Reply `command` | Reply `options` | Reply `payloads` | Reply `failure` |
|---|---|---|---|---|
| Success                                  | (none)          | n/a             | none             | none            |
| Exception                                 | (none)          | n/a             | n/a              | `failurepb.Failure` |

`OperationException` and `HandlerException` paths reuse the existing
failurepb machinery — `NexusFailureConverter` already produces
canonical `failurepb.Failure` for both cases (today's `*Direct`
already uses it). No changes there.

### What dies

- `src/Internal/Nexus/RoadRunner/Metadata.php` (file + the
  `RoadRunner/` directory if empty after removal).
- `NexusTaskHandler::startOperationDirect()` (lines ≈ 234-274).
- `NexusTaskHandler::cancelOperationDirect()` (lines ≈ 279-288).
- `NexusTaskHandler::encodeLinksMetadata()` private helper (lines
  ≈ 294-304).
- Test fixtures asserting on `RrMetadata::KIND_KEY` / `LINKS_KEY` in
  `tests/Unit/Nexus/IntegrationTestCase.php` (lines ≈ 23, 198-199,
  381, 383, 402).

### What stays

- `Router/InvokeNexusOperation.php` — same name and class. Body
  rewritten to use `handleStartOperation(Request)`.
- `Router/CancelNexusOperation.php` — same name and class. Body
  rewritten to use `handleCancelOperation(Request)`.
- `Router/CancelNexusOperationMethod.php` — unchanged.
- `NexusTaskHandler::handleStartOperation(Request): Response` /
  `::handleCancelOperation(Request): Response` — unchanged. They
  become the **single** handler path.
- `NexusFailureConverter`, `NexusLinkConverter`,
  `NexusInvocationRegistry`, `MethodCanceller`,
  `NexusEnvironment` — unchanged.
- `LinkParser::fromRaw` — used at the route layer to parse inbound
  `links` from JSON options before constructing the proto Request.
  Stays.
- `LinkParser::fromProto` / `::fromHeader` — different call sites,
  unchanged.

## Tasks

### Task 1: Add `NexusOperationStarted` outbound reply type

**Files:**

- `src/Worker/Transport/Command/Client/NexusOperationStarted.php`
  (new). Final class implementing `ResponseInterface` with the same
  shape `UpdateResponse` already uses (`getCommand()`,
  `getOptions(): array`, `getPayloads(): ?ValuesInterface`,
  `getFailure(): ?\Throwable`):
  - `command = "NexusOperationStarted"` (constant on the class,
    same pattern as `UpdateResponse::COMMAND_VALIDATED`)
  - `async: bool`, `token: ?string`, `links: array<array{url:string, type:string}>` —
    serialized into `options` as JSON
  - `payloads: ?ValuesInterface` — set on sync, null on async
- **Encoder strategy: dedicated branch.** Add an
  `instanceof NexusOperationStarted` arm to the switch in
  `src/Worker/Transport/Codec/ProtoCodec/Encoder.php`, mirroring the
  existing `instanceof UpdateResponse` arm at lines 77-90. Body sets
  `$message->setCommand($command->getCommand())`,
  `$message->setOptions(\json_encode($command->getOptions(), JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE))`,
  and conditionally `$message->setPayloads(...)`. Subclassing
  `UpdateResponse` is rejected — it is `final`; introducing a shared
  parent interface is heavier refactor than the precedent justifies.
- Verify `id` is set correctly — the reply correlates to the original
  request ID (existing pattern in `UpdateResponse`; the encoder calls
  `$message->setId($command->getID())` before the switch).

### Task 2: Rewrite `Router/InvokeNexusOperation.php` to use canonical handler

**Depends on:** Task 1.

**Files:** `src/Internal/Transport/Router/InvokeNexusOperation.php`.

- The route now constructs a canonical
  `\Temporal\Api\Nexus\V1\Request` from `$request->getOptions()` and
  `$request->getPayloads()`:
  ```php
  $options = $request->getOptions();
  $protoRequest = (new Request())
      ->setHeader($options['headers'] ?? [])
      ->setStartOperation(
          (new StartOperationRequest())
              ->setService($options['service'] ?? '')
              ->setOperation($options['operation'] ?? '')
              ->setRequestId($options['requestId'] ?? \bin2hex(\random_bytes(8)))
              ->setCallback($options['callback'] ?? '')
              ->setCallbackHeader($options['callbackHeaders'] ?? [])
              ->setLinks(NexusLinkConverter::toNexusProtoLinks(
                  LinkParser::fromRaw($options['links'] ?? null)))
              ->setPayload(self::firstPayload($request->getPayloads()))
      );
  ```
  **Helper:** `self::firstPayload(?ValuesInterface): ?Payload` is a
  small private static on the route (the existing
  `NexusTaskHandler::extractFirstPayload` is `private`; do not
  expose it just for this — keep the route self-contained).
- **MethodCanceller wiring (behavior preservation).**
  `handleStartOperation(Request)` today builds its own
  `OperationContext` from the proto, with no awareness of route-level
  canceller. To keep `OperationContext::isMethodCancelled()` working
  for impl handlers, extend `handleStartOperation` to accept an
  optional `?MethodCanceller`:
  ```php
  public function handleStartOperation(
      Request $request,
      ?MethodCanceller $methodCanceller = null,
  ): Response
  ```
  and thread it into the `OperationContext` constructor inside the
  handler. Route registers the canceller in `NexusInvocationRegistry`
  (so `CancelNexusOperationMethod` can find it) AND passes the same
  instance into `handleStartOperation`. Without this, behaviour
  silently regresses: cooperative-cancel via registry still fires,
  but impl can no longer observe it via context — a real loss.
- Call canonical
  `$response = $this->taskHandler->handleStartOperation($protoRequest, $canceller);`.
- Translate `Response.StartOperation.Variant`:
  - `getSyncSuccess()` → resolve with
    `new Client\NexusOperationStarted(async: false, token: null,
    links: $sync->getLinks(), payloads: <syncPayloadAsValuesInterface>)`.
  - `getAsyncSuccess()` → resolve with
    `new Client\NexusOperationStarted(async: true, token: $async->getOperationToken(),
    links: $async->getLinks(), payloads: null)`.
  - `getOperationError()` → **extract failure from Response and
    reject** the resolver: `$resolver->reject(<reconstructed
    OperationException>)`. The standard failurepb path then produces
    the `nexus.OperationError.{failed|canceled}` ApplicationFailureInfo
    that Go-side `nexusErrorFromFailure` already consumes. **Do NOT**
    change `handleStartOperation` to re-throw `OperationException` —
    that would diverge from sibling SDKs (Java/Go both return
    `Response.OperationError` from the canonical handler).
- On `NexusHandlerErrorException` (thrown by
  `handleStartOperation`): `$resolver->reject($e)` — standard
  failure-response path.
- `finally`: unregister canceller from `NexusInvocationRegistry`.
- Drop the `startOperationDirect` call entirely.

### Task 3: Rewrite `Router/CancelNexusOperation.php` to use canonical handler

**Depends on:** Task 1.

**Files:** `src/Internal/Transport/Router/CancelNexusOperation.php`.

- Construct `\Temporal\Api\Nexus\V1\Request` for cancel:
  ```php
  $options = $request->getOptions();
  $protoRequest = (new Request())
      ->setCancelOperation(
          (new CancelOperationRequest())
              ->setService($options['service'] ?? '')
              ->setOperation($options['operation'] ?? '')
              ->setOperationToken($options['operationToken'] ?? '')
      );
  ```
- Call `$this->taskHandler->handleCancelOperation($protoRequest);`.
- On success: `$resolver->resolve(EncodedValues::fromValues([]));`
  (empty success — same as today).
- On exception: `$resolver->reject($e);` — standard
  failure-response path.
- Drop the `cancelOperationDirect` call entirely.
- The existing `Service/Operation/OperationToken`-validators in the
  route are no longer needed at this layer (proto deserialization
  enforces type safety; the canonical handler validates business
  semantics). Drop them. (If the project later decides validation
  belongs at the route layer for both Start and Cancel symmetrically,
  add it then; currently neither route validates and the canonical
  handler is the single source of truth.)

### Task 4: Delete RR-bridge dead code

**Depends on:** Tasks 2–3 (so the bridge has zero live consumers).

**Files (DELETE):**

| Path / symbol | Reason |
|---|---|
| `src/Internal/Nexus/RoadRunner/Metadata.php` (file + empty `RoadRunner/` directory after) | `_rr_nexus_kind` / `_rr_nexus_links` constants — markers gone |
| `NexusTaskHandler::startOperationDirect()` (lines ≈ 234-274) | Single-path collapse — replaced by `handleStartOperation` |
| `NexusTaskHandler::cancelOperationDirect()` (lines ≈ 279-288) | Single-path collapse — replaced by `handleCancelOperation` |
| `NexusTaskHandler::encodeLinksMetadata()` private helper (lines ≈ 294-304) | JSON-in-metadata link encoder no longer needed |
| `use Temporal\Internal\Nexus\RoadRunner\Metadata as RrMetadata;` import in `NexusTaskHandler.php` | Class deleted |

**Files (UNCHANGED, explicit list — common review questions):**

- `Router/CancelNexusOperationMethod.php` — RR cooperative
  method-cancel, no Nexus analog.
- `NexusTaskHandler::handleStartOperation` /
  `::handleCancelOperation` — already canonical, just gain real
  callers.
- `NexusFailureConverter`, `NexusLinkConverter`,
  `NexusInvocationRegistry`, `MethodCanceller`,
  `NexusEnvironment` — unchanged.
- `LinkParser::fromRaw` / `::fromProto` / `::fromHeader` — unchanged.

### Task 5: Rewrite tests

**Depends on:** Tasks 2–4.

**Files:** `tests/Unit/Nexus/IntegrationTestCase.php` (rewrite of
metadata-marker assertions), new
`tests/Unit/Internal/Transport/Router/InvokeNexusOperationTestCase.php`,
`tests/Unit/Internal/Transport/Router/CancelNexusOperationTestCase.php`.

**Test placement** mirrors source path `src/Internal/Transport/Router/...`
per project convention; existing precedent:
`tests/Unit/Internal/Transport/Router/CancelNexusOperationMethodRouteTestCase.php`.
Suffix is `*TestCase.php` (non-Nexus-subsystem; the Nexus subsystem
suffix `*Test.php` applies only to `tests/Nexus/Unit/`, which this
plan does not touch).

- **Delete:** every `RrMetadata::KIND_KEY` / `LINKS_KEY` assertion
  in `IntegrationTestCase.php` (lines ≈ 23, 198-199, 381, 383, 402).
- **Add route-level integration tests:**
  - Sync handler result → outbound message has
    `command = "NexusOperationStarted"`, options `{async: false,
    links}`, payloads carry the result.
  - Async handler result → outbound message has
    `command = "NexusOperationStarted"`, options `{async: true,
    token, links}`, no payloads.
  - `OperationException` thrown by handler → outbound message has
    no command, `failure` set to a `failurepb.Failure` whose
    `ApplicationFailureInfo.Type` starts with
    `nexus.OperationError.`.
  - Generic `\Throwable` from handler → outbound message has no
    command, `failure` set (HandlerError shape).
  - Cancel happy path → empty success.
  - Cancel exception → failure response.
- Tests for `Router/CancelNexusOperationMethod` stay unchanged.
- Tests for `NexusTaskHandler::handleStartOperation` /
  `::handleCancelOperation`, `NexusFailureConverter`,
  `NexusLinkConverter`, `LinkParser` stay unchanged.

### Task 6: Update PHP-side docs

**Depends on:** Tasks 2–5.

**Files:** `docs/nexus/rr-integration.md`,
`docs/nexus/handler-side-sdk.md`.

- `docs/nexus/rr-integration.md`: rewrite the "RR → PHP
  (handler-side)" sections (`InvokeNexusOperation` and
  `CancelNexusOperation` — currently lines ≈ 19-65).
  - Document that the route is now a thin adapter over the canonical
    `handleStartOperation(Request) / handleCancelOperation(Request)`
    handlers.
  - Replace the response-side description (no more
    `_rr_nexus_kind` / `_rr_nexus_links`) with the Wire Contract
    response table from above.
- `docs/nexus/handler-side-sdk.md`: verify no references to
  `*Direct` methods or `RrMetadata` constants. Update if found.

`docs/nexus/spec.md` is intentionally **NOT** in this list — it
documents the Nexus protocol HTTP wire spec (vendor-neutral), not the
RR↔PHP transport. Metadata-marker constants never appeared there.

## Commit Plan

1. After Task 1: `feat(nexus): add NexusOperationStarted outbound reply type`.
2. After Tasks 2–3: `refactor(nexus): route through canonical handleStartOperation/handleCancelOperation`.
3. After Task 4: `refactor(nexus): drop *Direct methods and RrMetadata bridge`.
4. After Task 5: `test(nexus): cover NexusOperationStarted reply and route-level translation`.
5. After Task 6: `docs(nexus): document single-path canonical handler architecture`.

## Cross-team coordination

- **One coordination point.** The Go-side plan adds a
  `internal.NexusOperationStarted{Async, Token, Links}` reply DTO
  registered under command name `"NexusOperationStarted"`. This PHP
  plan emits that exact command name with the exact JSON shape
  documented above. Both sides should land in the same deploy
  window — there is no graceful fallback if the PHP worker ships
  before the Go plugin.
- **No proto / vendor coordination.** No
  `roadrunner-server/api` change, no
  `roadrunner-php/roadrunner-api-dto` bump.
- **Integration smoke** is end-to-end. Schedule a joint test pass
  after both sides' tasks merge into the `nexus` branches in their
  respective repos.

## Notes / risks

- **Single canonical path is the architectural goal**, not just the
  metadata cleanup. Both subgoals are achieved by the same change set.
  Sibling SDKs (sdk-go, sdk-java, sdk-typescript) all have a single
  handler path — keeping `*Direct` would re-introduce the divergence
  this plan exists to remove.
- **Failure path (OperationError, HandlerError) is unchanged.** They
  go through the existing `NexusFailureConverter` and ride in
  `Message.failure` exactly as today.
- **Validators dropped from the cancel route.** The canonical handler
  is the single source of truth for input validation.
- **`LinkParser::fromRaw` stays.** Its consumer (the route's parsing
  of inbound `links` from JSON options before constructing
  `nexuspb.StartOperationRequest`) is part of the request-side
  contract, which does not change.
- **`Router/InvokeNexusOperation.php` is not renamed.** The Go-side
  command name `InvokeNexusOperation` is preserved (codebase
  convention `Invoke<HandlerKind>`). Class name and file path
  unchanged on the PHP side.
