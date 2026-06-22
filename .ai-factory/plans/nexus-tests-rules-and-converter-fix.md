# Implementation Plan: Test-style audit, NexusFailureConverter typed-cause fix, no-test-skipping rule

Branch: nexus
Created: 2026-05-07

## Settings
- Testing: yes (this plan ends with a previously-incomplete acceptance test flipped to a hard assertion)
- Logging: standard
- Docs: no

## Why

Three coupled concerns surfaced after Plans A + B landed in the working tree:

1. **Style drift on the new tests.** Six test files (1 modified, 5 new) were
   written under time pressure. They need an audit pass against the project's
   accumulated rules (CLAUDE.md + `.ai-factory/rules/base.md` + skill-context
   for `aif-implement`): no essay docblocks, no informal abbreviations, full
   word identifiers, `if`-statements over short-circuit, etc.

2. **A `markTestIncomplete` is masking a real production gap.** The test
   `applicationFailureCausePreservesTypeMessageAndDetails` documents that
   `NexusFailureConverter::flattenCauseChain()` strips
   `ApplicationFailure::getType()` and `getDetails()` from the cause chain.
   That **must be a hard-failing assertion**, not a skipped marker — see
   surfaced rule in Phase 3.

3. **Need a rule that prevents this anti-pattern in the future.** When the
   task is "write tests" or "fix tests", you do not get to declare a test
   incomplete or skipped to bypass the failing path. Either fix the
   production code, narrow the assertion to what is genuinely outside the
   plan's scope, or reject the task. Saying "this gap exists, here is
   `markTestIncomplete`" is hiding the bug behind a green-ish CI line.

## Out-of-scope
- Fixing the *other* surfaced product gaps (Promise::all + at-least-one-fail
  surfaces TimeoutFailure; conflict-policy hardcoding; `cancelExisting`
  field). Those are separate plans.
- Rewriting the existing tests' assertions beyond style fixes — only the
  `markTestIncomplete` one gets a behavioral upgrade.
- Doc changes outside `CLAUDE.md` and `.ai-factory/rules/base.md`.

## Affected files

Test files in scope of the style audit:

```
tests/Acceptance/Extra/Nexus/SyncFailure/SyncFailureTest.php           (modified)
tests/Acceptance/Extra/Nexus/Idempotency/RequestIdIdempotencyTest.php  (new)
tests/Acceptance/Extra/Nexus/ParallelFailure/PartialFailureTest.php    (new)
tests/Acceptance/Extra/Nexus/ParallelCancel/CancelPropagationTest.php  (new)
tests/Acceptance/Extra/Nexus/ParallelMixed/MixedSyncAsyncTest.php      (new)
tests/Acceptance/Extra/Nexus/ReverseLinks/ReverseLinkTest.php          (new)
```

Production source touched by Phase 2:

```
src/Nexus/Internal/Failure/NexusFailureConverter.php                   (modify flattenCauseChain serializer)
src/Exception/Failure/FailureConverter.php                             (caller-side: reconstruct typed cause from richer JSON)
```

Rules updated by Phase 3:

```
CLAUDE.md                                                               (Tests section)
.ai-factory/rules/base.md                                               (Testing Patterns section)
```

## Tasks

### Phase 1 — Style audit on changed test files

- [x] **Task 1** — Audit and fix all 6 test files against project rules
      Files: all 6 listed under "Affected files" → "Test files in scope".
      Checklist (apply file by file, do not batch grep-replace blindly):
        - **Essay docblocks**: any class- or method-docblock longer than one line that
          restates the code or is task-narrative gets compressed to ≤1 line or removed.
          Spec carve-out: if the docblock captures a hidden constraint, an invariant
          discovered through testing, or a workaround tied to a specific server
          version, keep it; that is the intent of the rule.
        - **Informal abbreviations**: scan parameter/variable/property names for
          forbidden short forms (`$ctx`, `$opts`, `$ref`, `$attrs`, `$impl`, `$sce`,
          `$opt`). Standard exceptions: `$e` in catch, `$i`/`$j` loop counters, wire
          abbreviations (`RPC`, `URL`, `URI`, `ID`).
        - **`if`-statements over short-circuit side effects**: search for patterns
          `&& $x->y(...)`, `or throw`, `?:` used for control flow. Convert to explicit
          `if`/`throw`. PHPCS won't flag these — manual review.
        - **Full-message exception assertions**: `expectExceptionMessage()` only,
          no `expectExceptionMessageMatches()` regex fragments. (None in these tests
          today, but verify.)
        - **Tautological assertions**: every `self::assertSame/Equals` pair must be
          capable of failing on a real production change. Pure read-back-what-you-just-wrote
          asserts get deleted.
        - **`#[CoversClass]` / `#[UsesClass]`**: applies to `tests/Nexus/Unit/`
          subsystem tests, NOT acceptance tests. Acceptance tests do not need
          these annotations — verify the convention with the existing
          acceptance corpus before adding them.
        - **Test placement**: each acceptance test file lives in its own directory
          (one `*Test.php` per directory); CancelPropagation/MixedSyncAsync/ReverseLinks
          already follow this. Do not move existing files.
      Run `composer cs:diff` afterward (tests are not in cs scope, so this is a sanity
      check on whether anything style-fixable touched src/, which it shouldn't).
      Acceptance criterion: re-running the same test filter under
      `composer test:accept-fast` still produces `OK, but there were issues!` with
      6/7 PASS + 1 incomplete (Phase 1 must not change test behavior).

### Phase 2 — Make `applicationFailureCausePreservesTypeMessageAndDetails` pass

The plan author already knows the converter side is broken. Path: extend the
serializer to preserve typed-failure metadata, extend the caller-side reader to
reconstruct it, then flip the assertion.

- [x] **Task 2** — Reference research: how sdk-go and sdk-java preserve typed
      cause-chain through Nexus wire
      No code changes. Read in this order:
        - `../sdk-go/temporalnexus/operation.go` and the package's failure
          serialization (search for `ApplicationError` near `OperationError`
          encoding; the FailureConverter helpers are in `internal/failure_converter.go`).
        - `../sdk-java/temporal-sdk/src/main/java/io/temporal/internal/nexus/PayloadSerializer.java`
          and adjacent classes — look for how typed failure metadata flows on
          the OperationError details JSON.
        - `../sdk-java/temporal-sdk/src/test/java/io/temporal/internal/nexus/PayloadSerializerTest.java`
          to see assertion style for cause preservation.
      Output: a one-paragraph note in `.ai-factory/research/RESEARCH.md`
      under a new session entry summarizing:
        - JSON shape used to carry the typed failure metadata (probably a
          serialized `Failure` proto or a structured JSON object per cause level)
        - whether the OperationError-cause-chain ever surfaces an
          ApplicationFailure with intact `.type`/`.details` on the caller, or
          if Java/Go also flatten and only reconstruct the **outermost** cause
        - which side does the reconstruction (caller's FailureConverter, or the
          Nexus-specific reader)
      This is reconnaissance; do not start coding until it's written.

- [~] **Task 3** — Extend `NexusFailureConverter::flattenCauseChain()` to preserve
      typed-failure metadata (handler-side serializer)
      Implemented and reverted: the agent landed the protojson-Failure wire shape
      (matching sdk-go/sdk-java verbatim per Task 2 research). It passed unit tests
      (1706 OK after stale-shape tests dropped + 2 new round-trip tests added) but
      broke the caller-acceptance path: bundled RR Go binary v3 emits warning
      "RoadRunner version 3 not supported. Requires 2025.1.5 or higher" at startup
      and the frontend rejects/retries the new wire shape, surfacing as
      `TimeoutFailure(SCHEDULE_TO_CLOSE)` on caller. Rolled back to HEAD via
      `git checkout` to restore the existing pass-through behaviour.
      **Re-land path**: bump the RR binary in `composer get:binaries` to ≥2025.1.5,
      then re-apply the agent's diff (production source + unit tests) and flip
      Task 5's `markTestIncomplete`. The reverted diff lived in:
      `src/Nexus/Internal/Failure/NexusFailureConverter.php`,
      `src/Internal/Nexus/NexusTaskHandler.php`,
      `src/Internal/Transport/Router/InvokeNexusOperation.php`,
      `tests/Unit/Nexus/NexusTaskHandlerTestCase.php`,
      `tests/Nexus/Unit/Internal/Failure/NexusFailureConverterTest.php`,
      `tests/Unit/Exception/FailureConverterTestCase.php`.
      File: `src/Nexus/Internal/Failure/NexusFailureConverter.php`
      Today (line 111): each cause level becomes
      `{type: PHP class, message, trace}`. After Task 2 confirms the wire
      shape Java/Go use, replace it with a typed branch:
        - if `$cursor instanceof ApplicationFailure`: emit
          `{type: '<class>', message, trace, applicationFailureType: $cursor->getType(), nonRetryable: $cursor->isNonRetryable(), details: <serialized payload>}`
          (exact field names to mirror the spec from Task 2 — use Go's keys verbatim)
        - else if `$cursor instanceof TemporalFailure`: at minimum preserve
          the failure-type discriminator so downstream readers can branch.
        - else: keep the current 3-field shape.
      Constraints:
        - `details` serialization must round-trip a `ValuesInterface` payload
          encoded by the active `DataConverter`. The converter is injected
          lazily via `setDataConverter()` on `ApplicationFailure`, so use
          `$cursor->getDetails()` and serialize via the proto Payloads
          conversion that's already used in `FailureConverter` — DO NOT
          invent a fresh serialization.
        - Constructor signature changes / new dependencies: per skill-context rule
          "Constructor signature changes require call-site sweep in the same edit",
          grep for any direct callers of `flattenCauseChain` (currently
          `tracebackDetails` in the same class) and the test classes
          `NexusFailureConverterTest` / `FailureConverterTestCase`.
        - Add unit coverage in `tests/Nexus/Unit/Internal/Failure/NexusFailureConverterTest.php`:
          one test for the typed branch (ApplicationFailure cause →
          serialized JSON contains the typed fields with the exact spec
          keys), one test for non-Temporal causes (current shape preserved).

- [~] **Task 4** — Caller-side: reconstruct typed `ApplicationFailure` from the
      richer cause-chain JSON
      Reverted with Task 3 (same blocker; same rollback). The reader logic
      added to `InvokeNexusOperation::operationErrorToException()` was correct
      against the new wire shape but unreachable while the bundled RR binary
      rejects responses with `metadata.type=temporal.api.failure.v1.Failure`.
      File: `src/Exception/Failure/FailureConverter.php` (and any helper that
      reads Nexus OperationError details into PHP exceptions — locate via grep
      for `nexus.OperationError` and `flattenCauseChain` consumers; the existing
      caller-path reader is the load-bearing site.)
      Behavior:
        - When deserializing the Nexus OperationError cause chain, if a cause
          carries the `applicationFailureType` (or whatever spec-key Task 2
          settles on) field, build a `new ApplicationFailure(message, type,
          nonRetryable, details, previous)` instead of the generic
          `ApplicationFailure(message, 'nexus.OperationError.<state>', ...)`.
        - Wire `details` back through the `DataConverter` (lazy injection per
          existing pattern; the caller workflow's converter is already bound
          when this runs).
        - Preserve previous-chain pointers so a multi-level cause survives.
      Verification: same unit suite as Task 3, plus a roundtrip test in
      `tests/Unit/Exception/FailureConverterTestCase.php` that builds a
      flattened-chain JSON, asks the reader to rebuild it, and asserts
      `$rebuilt->getType() === 'CustomBusinessType'` and
      `$rebuilt->getDetails()->getValue(0, 'string') === 'detail-payload-marker'`.

- [~] **Task 5** — Flip `applicationFailureCausePreservesTypeMessageAndDetails`
      from `markTestIncomplete` to a real assertion
      Attempted: snял `markTestIncomplete`, прогнал — упало в `TimeoutFailure`
      (RR-binary blocker от Tasks 3+4). Восстановил `markTestIncomplete` с
      явной TODO-ссылкой на план и причиной (внешний precondition — RR binary
      version), что попадает под carve-out нового правила (CLAUDE.md "Tests
      must exercise real behaviour"). Существующий `callerCatchesNexusOperationFailureWithApplicationFailureCause`
      и весь Plan-A+B батч снова зелёные после rollback (1 transient flake на
      `CancelPropagationTest` — не моё изменение).
      File: `tests/Acceptance/Extra/Nexus/SyncFailure/SyncFailureTest.php`
      Remove the `markTestIncomplete(...)` call and the rationale comment
      that block the test. Keep the existing service + caller workflow
      (`SyncFailureRichCauseService`, `RichCauseCallerWorkflow`,
      `findApplicationFailureType`, `dumpChain`) — they were written for
      exactly this scenario.
      The test should pass against the converter changes from Tasks 3 + 4:
        - Cause chain contains an `ApplicationFailure` with
          `type === 'CustomBusinessType'`
        - `getOriginalMessage()` contains `'inner-business-message'`
        - `details->getValue(0, 'string') === 'detail-payload-marker'`
      Run only this test:
      ```
      FILTER='applicationFailureCausePreservesTypeMessageAndDetails'
      /opt/homebrew/Cellar/php@8.3/8.3.23/bin/php vendor/phpunit/phpunit/phpunit \
        --testsuite=Acceptance-Fast --configuration phpunit.xml.dist \
        --testdox --filter="$FILTER"
      ```
      Then run the full Plan-A+B batch to ensure nothing else regressed:
      ```
      FILTER='RequestIdIdempotencyTest|PartialFailureTest|CancelPropagationTest|MixedSyncAsyncTest|ReverseLinkTest|applicationFailureCausePreservesTypeMessageAndDetails'
      /opt/homebrew/Cellar/php@8.3/8.3.23/bin/php vendor/phpunit/phpunit/phpunit \
        --testsuite=Acceptance-Fast --configuration phpunit.xml.dist \
        --testdox --filter="$FILTER"
      ```
      Acceptance criterion: 7 tests, 0 errors, 0 failures, 0 incomplete, 35+ assertions.

### Phase 3 — Codify "no test-skipping" rule

- [x] **Task 6** — Extend the "Tests must exercise real behaviour" section in `CLAUDE.md`
      File: `CLAUDE.md` (project root, around the existing "Tests must exercise real behaviour" heading)
      Add a paragraph (concise, in the file's existing voice — no essay):

      > **Never use `markTestIncomplete()` or `markTestSkipped()` to bypass a
      > failing test when the task is to write or fix tests.** A red test that
      > documents a real gap is more valuable than a green-ish "incomplete"
      > marker that hides it. If the production code path the test targets is
      > genuinely broken, fix the production code first or narrow the assertion
      > to what *is* in the plan's scope. The only legitimate uses of skip /
      > incomplete are external preconditions outside the test author's
      > control (a missing extension, an unavailable third-party service, a
      > known-flaky upstream not yet addressable) — and those must be paired
      > with a one-line `// TODO(plan-or-issue-ref):` pointer to the unblocking
      > work. Hiding behind a skipped marker when the task is to test the
      > thing under test is a bug.

- [x] **Task 7** — Mirror the rule in `.ai-factory/rules/base.md`
      File: `.ai-factory/rules/base.md` (under the existing "Testing Patterns" section)
      Append a one-liner that links back to the CLAUDE.md paragraph:

      > - Never `markTestIncomplete`/`markTestSkipped` to dodge a failing test
      >   when the task is to write or fix tests; see CLAUDE.md "Tests must
      >   exercise real behaviour" for the long form.

      Note: `.ai-factory/skill-context/aif-implement/SKILL.md` is auto-generated
      by `/aif-evolve` and explicitly says "Do not edit manually." The rule
      will be picked up there on the next `/aif-evolve` run by virtue of being
      in CLAUDE.md and base.md. Do not hand-edit the skill-context file.

## Commit plan

7 tasks → split into 3 commits, one per phase, so review boundaries match the
risk profile (style cleanup → production change → rules text):

- **Commit 1 (after Tasks 1)**:
  `style(nexus-tests): apply project conventions across new caller-side acceptance tests`
- **Commit 2 (after Tasks 2-5)**:
  `fix(nexus): preserve ApplicationFailure type/details across the OperationError cause chain`
  Include the updated `SyncFailureTest::applicationFailureCausePreservesTypeMessageAndDetails`
  as part of the same commit so the production change ships with its load-bearing test.
- **Commit 3 (after Tasks 6-7)**:
  `docs(rules): forbid markTestIncomplete/Skipped as a way to dodge failing tests`

If Tasks 3 + 4 turn out to require non-trivial DataConverter rewiring, split
Commit 2 into two: Task 3 (handler serializer) → "feat(nexus-failure): preserve
typed-cause metadata in flattenCauseChain", then Tasks 4 + 5 → "fix(nexus-failure):
caller-side reconstructs ApplicationFailure type/details from the cause chain".

## Next

```
/aif-implement
```
