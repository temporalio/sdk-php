# Implementation Plan: Nexus OperationError structured cause through RR plugin

Created: 2026-05-13
Branch: nexus (no new branch — `git.create_branches: false`)

## Settings

- Testing: yes (Go unit tests in plugin + PHP acceptance fix)
- Logging: standard (match handler.go / nexus_caller.go neighbours — no extra DEBUG)
- Docs: yes (update `docs/nexus/rr-integration.md` after implementation)

## Why

Two SyncFailure acceptance tests in `sdk-php` fail end-to-end:
- `Extra\Nexus\SyncFailure\SyncFailureTest::callerCatchesNexusOperationFailureWithApplicationFailureCause`
- `Extra\Nexus\SyncFailure\SyncFailureTest::applicationFailureCausePreservesTypeMessageAndDetails`

Root cause is in `roadrunner-temporal/aggregatedpool/nexus.go::nexusErrorFromFailure` at lines 282-307: PHP sends a structured `temporal.api.failure.v1.Failure` with full recursive `cause` (incl. `ApplicationFailureInfo.Type='CustomBusinessType'` + `Details=Payloads([...])`), but the plugin **flattens the entire chain to a single string** via `failureToCauseString` → `errors.Str(...)` and stuffs that into `*nexus.OperationError.Cause`. The structural typing+details are destroyed before SDK-Go can re-serialize them.

For comparison, the Activity path in the same plugin module (`aggregatedpool/activity.go:145-152`) reads `retPld.Failure` directly and returns `temporal.GetDefaultFailureConverter().FailureToError(retPld.Failure)` — a structure-preserving wrapper whose `failureHolder` interface lets SDK-Go round-trip the proto bit-exact via `ErrorToFailure`. Nexus path should do the same.

## Out-of-scope

- Any change to `sdk-go` (caller-side `internal_nexus_task_handler.go` keeps its hardcoded `Type: "OperationError"` wrap; PHP tests adapt).
- `nexus_caller.go` (caller-side; this plan covers handler-side only).
- Cancel-path changes — only Start-path failure propagation.
- Adding new typed `NexusOperation*` commands to the goridge wire — current Message.Failure path works once structure is preserved.

## Affected files

Plugin:
- `roadrunner-temporal/aggregatedpool/nexus.go` — `nexusErrorFromFailure` rewrite; delete dead `failureToCauseString`, `failureTypeTag`, `failureChainMaxDepth`
- `roadrunner-temporal/aggregatedpool/nexus_test.go` — drop tests for deleted helpers; add round-trip tests; extend OperationError-state tests

Build:
- `rr-build/Makefile` — invoked as-is, no edits

PHP:
- `sdk-php/tests/Acceptance/Extra/Nexus/SyncFailure/SyncFailureTest.php` — walker in `AppFailureCallerWorkflow::run`
- `sdk-php/docs/nexus/rr-integration.md` — wire-semantics paragraph + table footnote

## Tasks

### Phase 1 — Plugin: structure-preserving error wrapping

- [x] **Task 1 — Swap string-flatten for FailureToError in nexusErrorFromFailure**
      File: `roadrunner-temporal/aggregatedpool/nexus.go:282-314`.
      Replace `cause := errors.Str(failureToCauseString(f))` with `cause := temporal.GetDefaultFailureConverter().FailureToError(f)` in all three branches (NexusHandlerFailureInfo, OperationError, fallthrough). The wrapper implements `failureHolder` (sdk-go/internal/error.go:340-343) and holds the original proto — `ErrorToFailure(thisErr)` at sdk-go/internal/failure_converter.go:71-75 short-circuits to that stored proto verbatim.
      Also set `Message: f.GetMessage()` on the `*nexus.OperationError` returned in the OperationError branch (line 302-306) — SDK-Go reads `unsuccessfulOperationErr.Message` at sdk-go/internal/internal_nexus_task_handler.go:259 and currently gets empty string because plugin doesn't set it.
      Verify no callers depend on old string-cause: `grep -rn "nexusErrorFromFailure" roadrunner-temporal --include="*.go"` should show only the one consumer at `aggregatedpool/nexus.go:229` (decodeStartReply).

- [x] **Task 2 — Remove dead helpers and their tests**
      Files: `roadrunner-temporal/aggregatedpool/nexus.go` (delete `failureToCauseString` lines 316-346, `failureTypeTag` lines 348-376, constant `failureChainMaxDepth` line 30), `roadrunner-temporal/aggregatedpool/nexus_test.go` (drop the two `failureToCauseString`-direct tests around lines 820-845).
      Keep `nexusOperationErrorTypePrefix` (still used for type-prefix recognition).
      `go build ./...` from plugin module root must stay green.

- [x] **Task 3 — Add round-trip unit tests for cause preservation**
      File: `roadrunner-temporal/aggregatedpool/nexus_test.go`.
      Add:
        - `TestNexusErrorFromFailure_OperationErrorSetsMessage` — feeds Failure with `Message='boom'`, asserts `oe.Message == 'boom'`. Regression guard for Task 1's Change 2.
        - `TestNexusErrorFromFailure_OperationErrorPreservesCauseProto` — feeds a recursive Failure (outer `ApplicationFailureInfo{Type='nexus.OperationError.failed'}`, cause `ApplicationFailureInfo{Type='CustomBusinessType', Details=Payloads(...)}`). Calls `temporal.GetDefaultFailureConverter().ErrorToFailure(oe.Cause)` and asserts via `proto.Equal` that result equals input Failure verbatim. Load-bearing assertion.
        - `TestNexusErrorFromFailure_HandlerErrorPreservesCauseProto` — analogous for handler-error branch.
        - Extend existing `TestNexusErrorFromFailure_OperationErrorFailed`/`_OperationErrorCanceled` with `assert.Equal(t, f.GetMessage(), oe.Message)`.
      Use `proto.Equal` from `google.golang.org/protobuf/proto` for verbatim-proto compares (NOT `assert.Equal` — proto messages have internal state that breaks reflect.DeepEqual).

### Phase 2 — Build

- [x] **Task 4 — Build rr binary and verify symlink**
      Step 1: `cd /Users/xepozz/IdeaProjects/temporalio/roadrunner-temporal && go test ./aggregatedpool/...` — quality gate, must be green.
      Step 2: `make -C /Users/xepozz/IdeaProjects/temporalio/rr-build build` — compiles cmd/rr against the local plugin via `replace` directive in `rr-build/roadrunner/go.mod:42`, emits `rr-build/rr`.
      Step 3: smoke-check `/Users/xepozz/IdeaProjects/temporalio/sdk-php/rr --version` — symlink already points to `rr-build/rr`, just confirms binary launches.

### Phase 3 — PHP-side test adjustment

- [x] **Task 5 — Walk extra wrap level in callerCatchesNexus test**
      File: `sdk-php/tests/Acceptance/Extra/Nexus/SyncFailure/SyncFailureTest.php`, `AppFailureCallerWorkflow::run` (lines 176-210).
      The caller chain after the plugin fix is: `NexusOperationFailure` → `ApplicationFailure(type='OperationError')` ← injected by SDK-Go → `ApplicationFailure(type='nexus.OperationError.failed')`. The test currently checks only `$cause = $e->getPrevious()` (the OperationError wrap) and looks for `nexus.OperationError.failed` in its type — fails. Replace with a walker that finds the first `ApplicationFailure` whose type starts with `nexus.OperationError.`, then asserts message contains `business-error`. Mirror the `findApplicationFailureType` pattern from `RichCauseCallerWorkflow` further down the same file (line 354-364). Keep the diagnostic-string return style intact.

### Phase 4 — Verification + docs

- [x] **Task 6 — Run end-to-end Nexus acceptance + unit suites**
      Result: both target tests ✔ in SyncFailureTest (7/7 sync tests green); AsyncFailureTest::callerReceivesFailureWhenHandlerWorkflowThrows occasionally cold-starts as the first sync-Nexus call in the batch but that is environmental and out of this plan's scope. Unit: 1710/1710 OK.
      `tests/runner.php vendor/bin/phpunit --testsuite=Acceptance-Fast --filter='SyncFailureTest' --testdox` — must be 9/9 (was 7/9).
      `tests/runner.php vendor/bin/phpunit --testsuite=Acceptance-Fast --filter='Extra.Nexus' --testdox` — broader regression check, must stay green except SyncFailure improvements.
      `composer test:unit` — must stay 1710/1710.

- [x] **Task 7 — Update docs/nexus/rr-integration.md**
      File: `sdk-php/docs/nexus/rr-integration.md`.
      Add to the wire-table near line 52 a note that **the caller-side chain has an extra `ApplicationFailure(type='OperationError')` wrap injected by SDK-Go** — to reach the original `nexus.OperationError.<state>` type, walk one level deeper.
      Replace dead reference `через NexusFailureConverter::operationExceptionToProto` with `через FailureConverter::mapExceptionToFailure (ветка NexusOperationException)`.
      Add a short paragraph documenting the new cause-preservation contract: plugin uses `temporal.GetDefaultFailureConverter().FailureToError(f)` so SDK-Go can round-trip the original proto bit-exact via `failureHolder`. For OperationError, plugin also sets `Message = f.GetMessage()` so SDK-Go's wrap carries the right outer message.
      Match existing Russian voice. No essay paragraphs — short bullets.

## Commit plan

3 commits — one per repository boundary:

1. **`fix(nexus): preserve OperationError cause-chain structure through RR plugin`** — Tasks 1-3 in `roadrunner-temporal`. Self-contained; merged independently in that repo.
2. **`chore: rebuild rr binary against updated nexus plugin`** — Task 4. Effectively no commit (binary lives under `rr-build/` which is gitignored in sdk-php); document the rebuild step in commit body or PR description in `roadrunner-temporal` commit. Plan-level checkpoint only.
3. **`fix(nexus): caller test walks SDK-Go wrap; doc the cause-preservation contract`** — Tasks 5 + 7 in `sdk-php`. Task 6 is verification, not committed.

If the plugin change is split across `roadrunner-temporal` PRs (one PR per task), squash Tasks 1-3 into a single PR titled the same as commit 1 above.

## Next

```
/aif-implement
```
