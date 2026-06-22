# Nexus RPC: Wire-Level Reference (English)

Companion to `docs/nexus/spec.md` (Russian, conceptual). This file zooms in on
the wire shapes you actually have to encode/decode and where each fact is
mirrored in the two reference SDKs:

- `J:` Java reference SDK at
  `/Users/xepozz/IdeaProjects/temporalio/nexus-rpc-sdk-java/nexus-sdk/src/main/java/io/nexusrpc`
- `P:` PHP reference SDK at
  `/Users/xepozz/IdeaProjects/temporalio/nexus-rpc-sdk-php/src`
- `G:` Go temporalnexus glue at
  `/Users/xepozz/IdeaProjects/temporalio/sdk-go/temporalnexus`

Spec source of truth: <https://github.com/nexus-rpc/api/blob/main/SPEC.md>.

---

## 1. Two-endpoint surface

Nexus is plain HTTP/1.1+ with two `POST` paths. There is no GET, no PATCH,
no separate poll. Long-running operations are modelled as a sealed union on
the StartOperation response.

| Verb | Path | Purpose |
| --- | --- | --- |
| `POST` | `/{service}/{operation}` | StartOperation |
| `POST` | `/{service}/{operation}/cancel` | CancelOperation |

`{service}` and `{operation}` are URL-path components, validated as printable
non-whitespace ASCII (0x21-0x7E):

- `P:` `Validation/ServiceNameValidator.php:30`,
  `Validation/OperationNameValidator.php:30`,
  `Validation/PrintableAsciiValidator.php:28-42`.

The handler interface is intentionally exactly two methods; see
`J: handler/Handler.java:8-25`. The PHP SDK exposes the same shape via
`P: Handler/HandlerInterface.php`.

---

## 2. StartOperation

### 2.1 Request

- Method: `POST /{service}/{operation}`.
- Body: the serialized operation input. Wire content-type is whatever the
  serializer chose (`application/json`, `application/octet-stream`,
  `application/x-protobuf`, etc.) and is stored verbatim on
  `HandlerInputContent.headers` together with any other request headers.
  See `J: handler/HandlerInputContent.java:14-107` and
  `P: Handler/HandlerInputContent.php`.
- Required header: `Nexus-Request-Id` — caller-provided opaque dedup key.
  PHP key constant: `P: Header.php:49`.
- Optional headers (caller -> handler): `Operation-Timeout`,
  `Request-Timeout`, `Nexus-Callback-Url`, any number of `Nexus-Callback-*`
  forwarders, `Nexus-Link`. See section 6.

### 2.2 Response — sealed union by HTTP status

The single most important fact about Nexus: **StartOperation returns
exactly one of three outcomes, discriminated by HTTP status, never both**.

| Status | Outcome | Body | Required headers |
| --- | --- | --- | --- |
| `200 OK` | Sync success | Serialized result (any content-type) | `Nexus-Operation-State: succeeded` (informational); `Content-*` from the serializer |
| `201 Created` | Async accepted | JSON `OperationInfo {token, state:"running"}` | `Content-Type: application/json` |
| `424 Failed Dependency` | Operation finished as `failed` or `canceled` | JSON `Failure` with `metadata.type=nexus.OperationError` | `Content-Type: application/json` |
| `4xx` / `5xx` | Handler error (transport-level) | JSON `Failure` with `metadata.type=nexus.HandlerError` | `Content-Type: application/json` |

The Java SDK encodes this as `OperationStartResult<R>` with a private
constructor and two factory methods `sync()` / `async()` — and explicitly
rejects building one with both fields set:
`J: handler/OperationStartResult.java:11-110` (build-time guard at line 105).

PHP mirror: `P: Handler/SyncOperationStartResult.php`,
`P: Handler/AsyncOperationStartResult.php`,
`P: Handler/OperationStartResult.php`.

`OperationInfo` JSON shape: `{"token": "...", "state": "running"}`. PHP
constructor enforces token validation: `P: OperationInfo.php:24-29`.

Token validity (printable ASCII 0x21-0x7E, non-empty):
`P: Validation/OperationTokenValidator.php:30-34`.

### 2.3 Sync vs async, design intent

Caller never asks for sync or async. The handler picks. That is the whole
point of the union: a service can switch any operation between modes
without breaking callers, as long as the caller is prepared to handle both
shapes.

The Go reference makes the same split with
`HandlerStartOperationResultSync` / `HandlerStartOperationResultAsync`
(see commentary in `G: operation.go:170-208`).

---

## 3. CancelOperation

### 3.1 Request

- Method: `POST /{service}/{operation}/cancel`.
- Token transport (pick one — both are spec-allowed):
  - `?token=<urlencoded>` query parameter, OR
  - `Nexus-Operation-Token: <token>` header (`P: Header.php:34`,
    `J: Header.java:27`).
- Body: empty.

The Java cancel-details DTO carries only the token:
`J: handler/OperationCancelDetails.java:7-69`. PHP equivalent:
`P: Handler/OperationCancelDetails.php`.

### 3.2 Response

- `202 Accepted`, empty body — also returned for "already canceled" and
  "already completed". Cancel is **idempotent**.
- `4xx`/`5xx` only for routing/auth/transport problems, not for "operation
  already in a terminal state".

The Go workflow-run impl illustrates: a malformed token returns
`HandlerErrorTypeBadRequest`, but the actual cancel call simply forwards to
`client.CancelWorkflow` and returns success regardless of whether it was a
no-op (`G: operation.go:144-161`).

---

## 4. OperationState (wire enum)

Wire values are **lowercase strings** and are part of the protocol. Don't
rename, don't title-case.

| Value | Meaning |
| --- | --- |
| `running` | Started, not yet terminal |
| `succeeded` | Completed successfully |
| `failed` | Completed with an `OperationError` of state=failed |
| `canceled` | Completed with an `OperationError` of state=canceled (American spelling — single 'l') |

PHP enum: `P: OperationState.php:17-35`. Java enum (uppercase identifiers,
serialized lowercase): `J: OperationState.java:4-13`. Used in
`Nexus-Operation-State` header on sync-success and on the async callback
POST, and as `details.state` inside `OperationError` failures.

---

## 5. Failure schema

Failures are recursive JSON. The same shape is used for `OperationError`
(StartOperation 424, callback POST body when terminal=failed/canceled) and
for `HandlerError` (any other 4xx/5xx).

### 5.1 Recursive shape

```json
{
  "message":    "<string, required>",
  "stackTrace": "<string|null>",
  "metadata":   { "<string>": "<string>" },
  "details":    "<arbitrary JSON value or null>",
  "cause":      <Failure or null>
}
```

Source: `J: FailureInfo.java:10-145`, `P: FailureInfo.php:17-70` (PHP also
adds `cause` recursion — note `J: FailureInfo` does not, only the PHP SDK
reifies `cause` as a typed nested object).

`metadata.type` is the discriminator between the two predefined shapes.
The metadata key constant is `"type"` in both SDKs:
`P: Exception/HandlerErrorFailure.php:29`, `P: Exception/OperationErrorFailure.php:30`.

### 5.2 OperationError

Terminal outcome of the operation. Sent on StartOperation `424` or in the
callback POST body when the async operation finishes failed/canceled.

```json
{
  "metadata": {"type": "nexus.OperationError"},
  "message":  "...",
  "details":  {"state": "failed" | "canceled"}
}
```

PHP canonical builder/reader: `P: Exception/OperationErrorFailure.php`
(constants at lines 27/30/33; `from()` at 44; `readState()` at 69).

The Java side splits this between `OperationException` (the throwable —
`J: OperationException.java:6-97`) and the surrounding HTTP transport
which packages it. The PHP SDK keeps the wire mapping in one file.

### 5.3 HandlerError

Transport-level error. Maps to a HTTP 4xx/5xx; the response body is the
same `Failure` JSON.

```json
{
  "metadata": {"type": "nexus.HandlerError"},
  "message":  "...",
  "details": {
    "type": "BAD_REQUEST",
    "retryableOverride": false
  }
}
```

`details.retryableOverride` is **omitted** when the handler did not
explicitly override the type's default retry behavior (see
`P: Exception/HandlerErrorFailure.php:54-60` — only emitted for
`Retryable` / `NonRetryable`).

Java reads/writes this from `HandlerException` plus the `Nexus-Request-Retryable`
header (legacy path; see `J: Header.java:42` and
`J: handler/HandlerException.java:222-243`).

### 5.4 Predefined HandlerError types and HTTP mapping

| Wire value | HTTP | Retry default |
| --- | --- | --- |
| `BAD_REQUEST` | 400 | no |
| `UNAUTHENTICATED` | 401 | no |
| `UNAUTHORIZED` | 403 | no |
| `NOT_FOUND` | 404 | no |
| `REQUEST_TIMEOUT` | 408 | yes |
| `CONFLICT` | 409 | no |
| `RESOURCE_EXHAUSTED` | 429 | yes |
| `INTERNAL` | 500 | yes |
| `NOT_IMPLEMENTED` | 501 | no |
| `UNAVAILABLE` | 503 | yes |
| `UPSTREAM_TIMEOUT` | 520 | yes |

PHP table (single source of truth for both directions):
`P: Exception/ErrorType.php:45-84`. Default-retryability table in PHP:
`P: Exception/HandlerException.php:85-100`; in Java:
`J: handler/HandlerException.java:222-243`.

`UNKNOWN` is reserved for codes not in the table; it maps **outbound**
to HTTP 500 (`P: Exception/ErrorType.php:55-56`).

`RetryBehavior` enum (`Unspecified`/`Retryable`/`NonRetryable`):
`P: Exception/RetryBehavior.php`, `J: handler/HandlerException.java:16-32`.

---

## 6. Canonical headers

All header names are case-insensitive on read; SDKs lowercase keys before
storing (`J: handler/OperationContext.java:262-270`,
`P: Internal/Headers.php:25-32`).

| Header | Where it appears | Purpose |
| --- | --- | --- |
| `Nexus-Request-Id` | StartOperation request | Caller-provided dedup key. Required. |
| `Nexus-Operation-Token` | CancelOperation request, async callback POST, server-side StartOperation 201 hint | The opaque token returned by an async start. |
| `Nexus-Operation-Id` | (deprecated) | Legacy alias for `Nexus-Operation-Token`. `P: Header.php:31`, `J: Header.java:20`. |
| `Nexus-Operation-State` | Sync success response, async callback POST | Lowercase `OperationState` value. |
| `Nexus-Operation-Start-Time` | Async callback POST | RFC 5322 / RFC 9110 IMF-fixdate. Optional; defaults to receive time. PHP format/parse: `P: Header.php:169-198`. |
| `Nexus-Operation-Close-Time` | Async callback POST | RFC 3339 with ms precision. PHP: `P: Header.php:140-164`. |
| `Operation-Timeout` | StartOperation request | Total time the caller is willing to wait for the *operation* (not just the HTTP call). Format `<number><ms\|s\|m>`. PHP parse: `P: Header.php:92-111`. |
| `Request-Timeout` | Any request | HTTP-level timeout, same format. |
| `Nexus-Callback-Url` | StartOperation request | URL to POST to when an async operation reaches a terminal state. `P: Header.php:61` — note this is `CALLBACK_PREFIX . 'Url'`. |
| `Nexus-Callback-*` | StartOperation request | Any other header with this prefix is forwarded to the callback POST **with the prefix stripped**. PHP comment at `P: Header.php:54-58`. The exception: `Nexus-Callback-Token` is sent on the callback as `Token: ...` (not as `Nexus-Callback-Token`). PHP: `P: Header.php:64`. |
| `Nexus-Link` | StartOperation request, sync success response, callback POST | RFC 8288 link, `<uri>; type="..."`. May repeat. PHP encoder: `P: Link.php:60-64`; PHP parser: `P: LinkParser.php:94-116`. |
| `Nexus-Request-Retryable` | HandlerError response (legacy) | Boolean. Modern path uses `details.retryableOverride` inside the JSON failure. PHP: `P: Header.php:69`; Java still references it as the canonical override mechanism — `J: Header.java:42`. |
| `Content-Type` | All bodies | See section 8. |

Token format note: server-to-server the operation token travels in a
**header**, not a JSON body field. That is why the token is restricted to
printable non-whitespace ASCII — header bytes (see section 9).

---

## 7. Async callback flow

When an operation that started async (StartOperation -> 201) reaches a
terminal state, the handler issues:

```
POST <Nexus-Callback-Url-from-start-request>
```

with:

- `Nexus-Operation-Token: <same token returned in OperationInfo>`
- `Nexus-Operation-State: succeeded | failed | canceled`
- `Nexus-Operation-Start-Time` (optional, IMF-fixdate)
- `Nexus-Operation-Close-Time` (RFC 3339 ms precision)
- Any `Nexus-Callback-*` headers from the original request, with the
  prefix stripped (so a caller header `Nexus-Callback-Authorization: x`
  arrives at the callback as `Authorization: x`).
- Any `Nexus-Link` headers handler wants to attach.
- Body: serialized result (success) or `Failure` JSON (failed/canceled).

The callback receiver responds `200 OK` with empty body on success.
Anything else means "redeliver" — handlers must be ready to retry.

The Go temporalnexus side wires this up by stuffing the operation token
into the callback header set before kicking off the workflow:
`G: operation.go:326-345` (note both `nexus-operation-id` and
`Nexus-Operation-Token` are written for backwards-compat with servers
older than Temporal 1.27.0).

---

## 8. Content types

Nexus does **not** prescribe a content-type. The handler/caller pair
chooses. Common values seen on the wire:

| Value | Meaning |
| --- | --- |
| (empty / absent) | `null` payload |
| `application/json` | JSON. Always used for spec envelopes (`OperationInfo`, `Failure`); see PHP constant `P: Header.php:75`. |
| `application/octet-stream` | Raw bytes |
| `application/x-protobuf; message-type=<fully.qualified.Name>` | Binary protobuf |
| `application/json; format=protobuf; message-type=<fully.qualified.Name>` | Protobuf encoded as JSON |

In Temporal-flavored Nexus, the body of a sync-success or callback POST is
a **sequence of `temporal.api.common.v1.Payload`s** — typically a single
payload — encoded by the worker's data converter. This convention lives
above the Nexus protocol; the spec itself is content-type-agnostic.

The PHP serializer abstraction is `Nexus\Sdk\Serializer\SerializerInterface`
(`P: Serializer/SerializerInterface.php`). The Java equivalent is
`io.nexusrpc.Serializer` (`J: Serializer.java`).

---

## 9. Operation token

### 9.1 What the spec says

- Opaque to the caller.
- Non-empty.
- Printable non-whitespace ASCII (0x21-0x7E) — because it must be valid as
  a single HTTP header value.

Validators: `P: Validation/OperationTokenValidator.php:30-34` ->
`P: Validation/PrintableAsciiValidator.php:28-42`.

### 9.2 What Temporal puts inside

Temporal's workflow-run-operation token is a base64url-encoded JSON
struct: `G: token.go:17-63`.

```json
{
  "t":   1,            // operationTokenType, only "workflow run" exists
  "ns":  "<namespace>",
  "wid": "<workflow id>"
}
```

Encoding: `base64.URLEncoding.WithPadding(base64.NoPadding)` applied to
`json.Marshal(token)`. The `v` field is reserved for future versioning and
**must be absent** in v1 tokens — `loadWorkflowRunOperationToken` rejects
tokens where `v != 0` (`G: token.go:55-57`).

This is internal to Temporal — caller code must never parse it.

---

## 10. Links

### 10.1 Header format (RFC 8288)

```
Nexus-Link: <https://example.com/resource>; type="com.example.MyType"
```

- Required parameter: `type`. Other parameters tolerated.
- `type` is a string. Java's `Link` doc constrains it to alphanumeric +
  `_ . /` (`J: Link.java:78-80`); PHP's `Link` only requires non-empty
  (`P: Link.php:31-33`).
- Quoted-string with `\\` and `\"` escaping is supported in the parser:
  `P: LinkParser.php:243-275`.

### 10.2 Multiple links

A single `Nexus-Link` header value may contain a comma-separated list of
entries; the same header may also repeat. Both are valid. Splitting
respects nesting inside `<...>` and `"..."`:
`P: LinkParser.php:133-181`.

The header builder concatenates with `, ` separator:
`P: LinkParser.php:123-126`.

### 10.3 Where links flow

- Caller -> handler on StartOperation request (caller hint: "this
  request relates to my X").
- Handler -> caller on StartOperation response (sync or async) — to
  attach handler-side resource references.
- Handler -> caller on async callback POST.

PHP carries them as `Link[]` on `OperationStartDetails`,
`OperationContext`, etc. Java mirror: `J: handler/OperationStartDetails.java:55-60`,
`J: handler/OperationContext.java:64-66, 117-134`.

### 10.4 Strict parsing

The PHP `LinkParser` rejects malformed header values with
`HandlerException(BadRequest)`, never silently drops entries
(`P: LinkParser.php:31-115`). This matches the project rule in
`CLAUDE.md` "Validation policy".

---

## See also

- `docs/nexus/spec.md` (Russian) — conceptual overview and PHP-ergonomic
  guidance; do not duplicate content from there.
- `docs/nexus/handler-side-sdk.md` — how this protocol surfaces in the
  Temporal PHP SDK's user-facing Nexus API.
- `docs/nexus/rr-integration.md` — RoadRunner adapter that translates
  these wire shapes into the PHP-side `InvokeNexusOperation` /
  `CancelNexusOperation` routes.
