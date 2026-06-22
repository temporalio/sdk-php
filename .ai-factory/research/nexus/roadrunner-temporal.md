# roadrunner-temporal — Nexus integration notes

Notes about the Go-side RoadRunner Temporal plugin (`rrtemporal`) that bridges
PHP workers to the Temporal server for Nexus.

> Sibling SDK source: `/Users/xepozz/IdeaProjects/temporalio/roadrunner-temporal/`.

## What is wired today

### Caller-side (PHP workflow → Nexus operation)

`aggregatedpool/handler.go` already implements both arms:

- `aggregatedpool/handler.go:533` — `case *internal.ExecuteNexusOperation:`
  - extracts endpoint/service/operation/payloads/header
  - calls `wp.env.ExecuteNexusOperation(params, completionCallback, startedCallback)`
  - registers cancel via `wp.canceller.Register(msg.ID, ...)` →
    `wp.env.RequestCancelNexusOperation(nexusSeq)`
- `aggregatedpool/handler.go:564` — `case *internal.GetNexusOperationStarted:`
  - looks up by `command.ID` in `wp.nexusStarted` (the started registry)
  - on resolve, pushes `NexusStartEnvelope{Async, Token}` back to PHP

### Caller-side helpers

`aggregatedpool/nexus_caller.go` (66 lines):

- `type NexusStartEnvelope struct { Async bool; Token string }`
  — wire envelope returned to PHP for `GetNexusOperationStarted`
- `makeNexusStartedRegistryCallback(startMsgID uint64)` — pushes start
  events into the registry keyed by message ID
- `makeNexusCompletionResponseCallback(startMsgID uint64)` — resolves the
  original `ExecuteNexusOperation` request when the operation completes
- `pushStartEnvelope(awaitMsgID uint64, envelope NexusStartEnvelope)` —
  emits the envelope to the PHP queue

### Started registry

`registry/nexus_started.go` + `registry/nexus_started_test.go` — keeps
in-flight start callbacks keyed by message ID. PHP's
`GetNexusOperationStarted` request resolves through this registry.

### Handler-side (Temporal server → PHP handler)

- `aggregatedpool/nexus.go` (518 lines) — handler dispatch.
- `aggregatedpool/nexus_handler_test.go` — extensive unit tests
  (`InvokeNexusOperation`, `CancelNexusOperation`, `CancelNexusOperationMethod`,
  link metadata round-trips, error mapping).
- `aggregatedpool/nexus_error_mapping_test.go` — failure-conversion tests:
  `nexusErrorFromFailure(f)` produces `*nexus.HandlerError` and
  `*nexus.OperationError` with the right shape.

### Internal protocol

- `internal/protocol.go` — defines `ExecuteNexusOperation`,
  `GetNexusOperationStarted`, `InvokeNexusOperation`,
  `CancelNexusOperation`, `CancelNexusOperationMethod` envelope types.
- `internal/nexus_protocol_test.go` — protocol-level encoding tests.
- `internal/worker_info_nexus_test.go` — `GetWorkerInfo` extension for
  Nexus services.

### Misc

- `dataconverter/` — payload serializer also serves Nexus payloads.
- The samples in `samples-php/` (branch `nexus`) work against this RR build.

## Wire flow

### PHP caller starts a Nexus operation

```
PHP workflow yields ExecuteNexusOperation{endpoint, service, op, payload, header}
  → goridge → handler.go:533 case
  → wp.env.ExecuteNexusOperation(params, completion, started)
  → Temporal server schedules NexusOperationScheduled event
```

### Started signal arrives

```
Temporal server → ExecuteNexusOperation event-loop callback
  → makeNexusStartedRegistryCallback fires
  → registry stores {token, err}
PHP yields GetNexusOperationStarted{ID}
  → handler.go:564 case
  → registry.Listen(command.ID, ...)
  → pushStartEnvelope({Async, Token}) to PHP
```

### Completion arrives

```
Temporal server → ExecuteNexusOperation completion callback
  → makeNexusCompletionResponseCallback fires
  → resolves the original ExecuteNexusOperation request
  → PHP receives the resolved future
```

### Cancellation

```
PHP workflow cancels its Nexus future
  → canceller.Discard(msg.ID)
  → registered closure: wp.env.RequestCancelNexusOperation(nexusSeq)
  → Temporal server emits NexusOperationCancelRequested
```

## What this means for PHP SDK work

- The case arms `*internal.ExecuteNexusOperation` and
  `*internal.GetNexusOperationStarted` are already implemented — no work
  needed on the RR side for caller-side wire support.
- The SDK side just has to produce the right `ExecuteNexusOperation` and
  `GetNexusOperationStarted` request envelopes; everything below is
  already implemented in RR.
- For end-to-end testing, run against this RR build —
  `samples-php` (branch `nexus`) `Makefile` orchestrates it;
  `app/tests/Feature/Nexus/` covers it.

## Files to bookmark

| File | Why |
|---|---|
| `aggregatedpool/handler.go:520-575` | The two case arms (caller + start poll) |
| `aggregatedpool/nexus_caller.go` | Started/completion callbacks + envelope |
| `aggregatedpool/nexus.go` | Handler-side dispatch (518 lines) |
| `aggregatedpool/nexus_handler_test.go` | Comprehensive handler tests |
| `aggregatedpool/nexus_error_mapping_test.go` | Failure-conversion tests |
| `registry/nexus_started.go` | Started registry impl |
| `internal/protocol.go` | Envelope type definitions |
