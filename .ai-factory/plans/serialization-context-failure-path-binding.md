# Serialization Context — Failure-Path Binding

**Parent feature:** [serialization-context-converters-codecs.md](serialization-context-converters-codecs.md)
**Issue:** [temporalio/sdk-php#587](https://github.com/temporalio/sdk-php/issues/587) → cross-SDK [temporalio/features#434](https://github.com/temporalio/features/issues/434)
**Branch:** none (config `git.create_branches: false` — work on current branch `master`)
**Created:** 2026-06-22

## Settings

- **Testing:** yes — unit (where cheap) + acceptance (failure-path E2E)
- **Logging:** verbose for new error-path code, but no logging inside `DataConverter`/`EncodedValues`
  hot loops; failure paths already carry the exception as the diagnostic
- **Docs:** yes — add a "failure paths" note to the SerializationContext section in `docs/data-conversion/`
- **Roadmap linkage:** none (no ROADMAP.md configured)

## Problem

A code review against sdk-java found the SerializationContext feature binds context on every
**success** seam but **drops it on the failure / terminal seams** — exactly where Java keeps it.
For a context-aware converter (signing/encrypting keyed on workflowId), error paths break or are
silently inconsistent with success paths. The default Json/Proto/Binary/Null converters ignore
context, so existing users are unaffected; the gap only bites the custom converters the feature
exists to enable.

Mechanism that makes the fix cheap: `FailureConverter::createFailureException` builds failure
details lazily as `EncodedValues::fromPayloads($info->getDetails(), $converter)`
(`src/Exception/Failure/FailureConverter.php:175`). So a context can be attached either by passing a
**context-bound converter** into `mapFailureToException(...)`, or by calling
`setSerializationContext()` on the details `EncodedValues` — no new converter plumbing is needed.

## Verified gaps (from the review)

| # | Seam | File:line | Today | Java |
|---|------|-----------|-------|------|
| 1 | Workflow FAILED result decode (client) | `src/Internal/Client/WorkflowStub.php:673` | bare converter; CANCELED sibling (`:558`) binds context | binds workflow context |
| 2 | Activity/child failure decode (in workflow) | `src/Internal/Transport/Client.php:71` (+ `FailureResponseInterface` encode) | success binds, failure drops context both ends | binds activity context |
| 3 | Update failure (worker + client) | `InvokeUpdate.php:111`, `ResponseToResultMapper.php:74` | success binds, failure bare | binds workflow context |
| 4 | Async/lazy update poll | `src/Client/Update/UpdateHandle.php:150,157` | whole path bare (file untouched) | binds workflow context |

Cleanup (separate optional tail): #6 `terminate` details encode (`WorkflowStub.php:426`), #7
clone-per-value in `EncodedValues::converter()`, #8 `describe()` memo decode (`WorkflowStub.php:484`),
#9 dead `!== ''` guard (`ScheduleMapper.php:38`), #10 dead `$serializationContext` field
(`DataConverter.php:27`).

> **Out of scope (documented limitation, decided earlier):** #5 child-workflow auto-generated-id
> asymmetry — PHP assigns the id server-side, so an auto-id child encodes contextless. Matches the
> parent plan's "Known limitations". Not addressed here; revisit only if a client-side id generator
> is wanted.

## Fiber/Generator note (skill-context)

The failure-path acceptance test exercises a workflow that **throws**, but the assertions are on
client-side decode and worker-side encode of *failure details* — both mode-agnostic. The throw
mechanism is identical in Generator and Fiber mode, so a byte-near-identical Fiber mirror would add
zero coverage. Per the project Fiber-mirror rule, **do not** create a Fiber mirror for this test;
record the decision in the test file header comment-free (the test name carries intent). No
`src/Workflow/`, `src/Internal/Workflow/Process/Scope.php`, `Process.php`, `StackRenderer.php`, or
`src/Experiments/Fibers/*` files are touched by Tasks 1–4.

## Tasks

### Phase 1 — Failure-path context binding (the real bugs)

- [x] **Task 1 (#1)** — `src/Internal/Client/WorkflowStub.php`, `mapWorkflowFailureToException()`
  (~`:664-674`): for the `WorkflowExecutionFailedException` case, decode the failure with a
  context-bound converter — `FailureConverter::mapFailureToException($failure->getFailure(),
  SerializationContextBinder::bind($this->converter, new WorkflowSerializationContext(
  $this->clientOptions->namespace, $this->getExecution()->getID())))`. Mirror the already-correct
  CANCELED branch (`:558-560`). Add `use` for `SerializationContextBinder`. **Log** (DEBUG) the bound
  workflowId once on the failure path.

- [x] **Task 2 (#3 client)** — `src/Internal/Client/ResponseToResultMapper.php:74`: in the
  `$failure !== null` branch, when `$namespace !== null`, pass
  `SerializationContextBinder::bind($this->converter, new WorkflowSerializationContext($namespace,
  $workflowExecution->getID()))` into `mapFailureToException(...)` instead of the bare
  `$this->converter`. Symmetric with the success branch (`:54-59`). *(blocked by nothing; independent)*

- [x] **Task 3 (#3 worker + #4)** — two edits, same theme:
  - `src/Internal/Transport/Router/InvokeUpdate.php` failure closure (`:111-118`): capture
    `$serializationContext` in the `use(...)`, and if `$err instanceof TemporalFailure` with
    non-empty details, `$err->getDetails()->setSerializationContext($serializationContext)` (walk the
    `getPrevious()` cause chain). This makes `mapExceptionToFailure(...).toPayloads()` bind context on
    encode regardless of the null `values` carrier.
  - `src/Client/Update/UpdateHandle.php` `fetchResult()` (`:150,157`): build
    `$context = new WorkflowSerializationContext($this->clientOptions->namespace,
    $this->getExecution()->getID())`; on success call `setSerializationContext($context)` on the
    result `EncodedValues`; on failure pass `SerializationContextBinder::bind($this->converter,
    $context)` into `mapFailureToException(...)`. Add `use` imports.

- [x] **Task 4 (#2)** — activity/child-workflow failure symmetry (most involved):
  - **Encode side** — `src/Internal/Transport/Router/InvokeActivity.php` `catch (\Throwable $e)`
    (`~:130`): if `$e instanceof TemporalFailure` with non-empty details, set the already-built
    `$serializationContext` (the `ActivitySerializationContext` from `:76`) on `$e->getDetails()`
    (walk `getPrevious()`), so the encoder's `FailureResponseInterface` branch serializes failure
    details with activity context.
  - **Decode side** — `src/Internal/Transport/Client.php` `dispatch()` failure branch (`:70-71`):
    when `$serializationContext !== null` and `$response->getFailure()` is a `TemporalFailure`, call
    `setSerializationContext($serializationContext)` on its details `EncodedValues` (and each
    `getPrevious()` in the cause chain) before `$deferred->reject(...)`. No converter is needed here —
    the details already hold the base converter from the Decoder; context layering is enough.
  - Extract the cause-chain walk into a small private helper reused by Tasks 3 and 4
    (e.g. a static `applyContextToFailureDetails(TemporalFailure $f, ?SerializationContext $c): void`
    in `SerializationContextBinder` or a dedicated internal helper) to avoid copy-paste. **Log** (DEBUG)
    when context is applied to a failure's details. *(blocked by nothing, but land after 1–3)*

### Phase 2 — Test & verify

- [x] **Task 5** — extend `tests/Acceptance/Extra/DataConverter/SerializationContextTest.php` (reuse
  the existing `SignedPayloadConverter`) with failure-path coverage:
  1. a workflow that throws `ApplicationFailure` carrying a `SignedDto` detail → client `getResult()`
     must surface `WorkflowFailedException` whose decoded detail signature equals the workflowId
     (today it throws the wrong `LogicException` from the converter).
  2. an update handler that rejects with a signed-detail `ApplicationFailure` → assert the decoded
     failure detail signature matches.
  3. an activity that throws a signed-detail failure → assert the workflow receives it decoded with
     the activity context.
  Assertions on full messages, real round-trips (no tautologies). No Fiber mirror (see note above).
  No `markTestSkipped`/`markTestIncomplete`. *(blocked by 1, 2, 3, 4)*

> **Implementation note (during /aif-implement):** the cause-chain walk was implemented as a
> `setSerializationContext(?SerializationContext)` method on the `TemporalFailure` hierarchy
> (base recurses through `getPrevious()`; `ApplicationFailure`/`CanceledFailure`/`TimeoutFailure`
> set it on their `details`/`lastHeartbeatDetails`), mirroring the existing `setDataConverter()`
> pattern — cleaner than a static type-switching helper, and reused by every failure site (#1–#4).
> Activity failures arrive wrapped in `ActivityFailure` (cause = the user's `ApplicationFailure`);
> the recursion handles that. Full pyramid green (unit 699, arch, func 153, accept-fast 117,
> accept-slow 47, SerializationContext 3); psalm no new errors; cs clean.

- [x] **Task 6** — full verification + docs:
  `composer test:unit && composer test:arch && composer test:func && composer test:accept-fast &&
  composer test:accept-slow`, then `composer psalm` and `composer cs:fix`. Update
  `docs/data-conversion/` SerializationContext section: state that failure/terminal payloads
  (workflow-failed result, update failure, activity failure details) now carry context, and keep the
  documented exclusions (memo/SA, child auto-id). *(blocked by 5)*

### Phase 3 — Optional cleanup (skippable tail — land or drop independently)

- [ ] **Task 7 (#6)** — `src/Internal/Client/WorkflowStub.php` `terminate()` (`:426`): set a
  `WorkflowSerializationContext` on the details `EncodedValues` before `toPayloads()`, matching Java.

- [ ] **Task 8 (#8)** — `describe()` (`:484-496`): FIRST confirm whether sdk-java binds workflow
  context for the describe response memo/header decode. If yes, wrap the three mappers' converter with
  `SerializationContextBinder::bind(...)`. If the parent plan's "memo/SA not contextualized" decision
  covers this, document it and close without code change. Decision task — do not bind blindly.

- [ ] **Task 9 (#7)** — `src/DataConverter/EncodedValues.php` efficiency: `converter()` re-binds (clones
  the whole `DataConverter` + re-wraps members) on every `getValue()`/`valueToPayload()`. Memoize the
  bound converter (invalidate when `converter`/`serializationContext` changes) so an N-payload
  collection binds once, not N times. Must not change observable behaviour; keep `getValues()`'s
  existing single-bind.

- [ ] **Task 10 (#9, #10)** — two micro-cleanups: drop the dead `$action->workflowId !== ''` term in
  `src/Internal/Mapper/ScheduleMapper.php:38` (id always defaults to `Uuid::v4()`); and either remove
  the never-read `$serializationContext` field on `src/DataConverter/DataConverter.php:27` or reduce
  its role to the documented identity-guard for `withSerializationContext()`'s early return.

## Commit Plan

- **Commit 1** (Tasks 1–3): `fix(data-converter): bind serialization context on workflow/update failure decode`
- **Commit 2** (Task 4): `fix(data-converter): carry serialization context through activity/child failure paths`
- **Commit 3** (Tasks 5–6): `test: serialization context failure-path E2E + docs`
- **Commit 4** (Tasks 7–10, optional): `refactor(data-converter): serialization context cleanups`

## Risks & decisions

- **Cause-chain walk:** failures nest (`ApplicationFailure` → `previous`). The helper must apply
  context to each level's details, not just the top — otherwise nested signed details still mismatch.
- **`setSerializationContext` vs context-bound converter:** prefer passing a bound converter where we
  control the `mapFailureToException` call (Tasks 1, 2, 4-decode-via-already-bound); use
  `setSerializationContext` on existing `EncodedValues` where the converter is already attached
  (Tasks 3-worker, 4-encode). Both routes end at the same `EncodedValues::converter()` binder.
- **No behaviour change for default converters:** all four built-in converters ignore context, so the
  full pyramid must stay green with zero diff in default-converter payloads. Task 6 is the gate.
- **Replay safety:** context is derived from deterministic `WorkflowInfo`/`ActivityInfo` — unchanged
  from the parent feature; no new non-determinism.
- **`describe()` (#8) is a genuine open question** — resolve by reading sdk-java, not by guessing.

## Next steps

```
/aif-implement

CONTEXT FROM /aif-plan:
- Plan file: .ai-factory/plans/serialization-context-failure-path-binding.md
- Testing: yes (unit + acceptance failure-path)
- Logging: verbose (error-path code only; none in converter hot loops)
- Docs: yes (docs/data-conversion SerializationContext failure-path note)
```
