# Implementation Plan: Remove `yield` from "Fiber" tests and prove they actually run inside a Fiber

Branch: `fibers`
Created: 2026-05-23
Source finding: `.ai-factory/research/fibers-review.md` — **C2** (with bonus fold-in of one bullet from **M12**)

## Settings

- Testing: yes (this plan is itself a test-quality plan; the deliverable is correct tests, verified via `composer test:accept-fast`)
- Logging: standard
- Docs: no

## Why

Two test files under `tests/Acceptance/Extra/.../Fibers/` claim Fiber-mode
coverage. They don't actually provide it. PHP turns any function body
containing `yield` into a `\Generator`. The workflow method therefore returns
a Generator, the Fiber-bridge in `Scope::createFiberHandler` wraps the call,
the Fiber body returns the Generator without iterating, the bridge sees
`isTerminated()=true`, and the **legacy Generator path drives the workflow**
— not the Fiber path. The tests pass, but they exercise the wrong runtime.

In `RawValueTest.php:49` specifically: `$activity` is a `FiberProxy` whose
`__call` invokes the inner activity proxy and then calls
`FiberHelper::await(...)` on the resulting `PromiseInterface`. But
`FiberHelper::await` only suspends a Fiber when the surrounding
`ScopeContext::isFiberMode()` returns `true`; otherwise it returns the raw
`PromiseInterface` unchanged (see `src/Experiments/Fibers/FiberHelper.php:28-39`).
Because the workflow body has `yield`, it runs on the Generator path, so
`fiberMode === false`, so `FiberHelper::await` falls through and yields a
raw promise. The test *appears* to work because the Generator path handles
`yield <promise>` exactly the way you'd expect — but the Fiber facade,
proxy, and bridge are doing nothing of value. The test could be rewritten
to use the base `\Temporal\Workflow` facade and produce identical
behavior. It claims Fiber coverage and provides none.

Both files need to be rewritten as plain Fiber bodies (no `yield`, no
`\Generator` return type) **and** carry a positive assertion that the
workflow body executed inside a Fiber. Without the sentinel, the next
person reading the test cannot tell the difference between "we removed
`yield` and the Fiber path runs" and "we removed `yield` and the body
silently degraded to a sync function that happened to work".

The same `ContextTest.php` file also has one `assertStringContainsString`
violation of the CLAUDE.md rule "Exception tests: assert full messages,
not fragments" on line 55. That's the M12 bullet for this file; it's
trivially fixable in the same diff and keeps the file rule-compliant
end-to-end after this plan lands.

## Out-of-scope

- The other two M12 sites (`UntypedStubTest.php:100-104`,
  `UpdateWithStartTest.php:61`). Those are in different test files and
  belong to a separate `m12-full-message-assertions` plan, so this plan
  doesn't touch them.
- ContextTest.php:69's `assertStringContainsString('exception-in-execute', …)`
  is **not** in scope — the prompt only flagged line 55. If line 69 is also
  a fragment-vs-full-message issue, it lands with the M12 plan, not here.
- Renaming workflows, changing task queues, or any of the C3/C4 work.
- Changing the production code in `src/Experiments/Fibers/*` or
  `src/Internal/Workflow/Process/Scope.php`. The sentinel is read-only.
- Adding new Fiber-mode tests for other scenarios (H10 deep-stack, etc.).

## File overlap

This plan **touches**:
- `tests/Acceptance/Extra/DataConverter/Fibers/RawValueTest.php`
- `tests/Acceptance/Extra/Interceptors/Fibers/ContextTest.php`

Safe to parallelize with other Fiber-fix plans (C1 cleanup, C3 logger
rename, C4 task-queue rewrites, C5 schedule deletions, C6/C7/C8 runtime
fixes, M12 other files). No production source files are touched.

## Affected files

```
tests/Acceptance/Extra/DataConverter/Fibers/RawValueTest.php   (modify FeatureWorkflow::run + check())
tests/Acceptance/Extra/Interceptors/Fibers/ContextTest.php     (modify TestWorkflow::handle + line 55 assertion)
```

No new files. No deletions. No production sources.

## Background — design of the Fiber-mode sentinel

We need a positive signal that the workflow body executed inside a Fiber,
not on the Generator path. Two viable strategies exist; this plan picks
(A) because it is the most direct and doesn't require touching interceptor
wiring:

**(A) Sentinel value read inline from the workflow body, returned to the test.**
Inside the workflow method, capture the runtime Fiber state by reading the
`ScopeContext`. The Fiber facade exposes `Workflow::getCurrentContext()`
which returns a `WorkflowContextInterface`. At runtime (inside a Fiber-mode
scope) the concrete type is `Temporal\Internal\Workflow\ScopeContext`,
which carries `isFiberMode(): bool` (see `src/Internal/Workflow/ScopeContext.php:111`).

The body therefore does:

```php
$context = Workflow::getCurrentContext();
$fiberMode = $context instanceof \Temporal\Internal\Workflow\ScopeContext
    && $context->isFiberMode();
```

…and surfaces the boolean back to the test:
- `RawValueTest`: change the return shape to an array carrying both the
  round-tripped `RawValue` and the captured `$fiberMode` flag.
- `ContextTest::instanceInContext`: extend the existing returned array
  with an `'fiberMode' => $fiberMode` entry.

The test then asserts `$fiberMode === true`. If a future regression makes
the workflow body run under the Generator path again, the assertion fails
hard at the test level — instead of silently degrading.

**Why not strategy (B) — interceptor that captures the mode at handler entry.**
This would require touching `WorkerServices::interceptors` in `ContextTest.php`
to capture the bridge state from outside, and `RawValueTest.php` has no
interceptor scaffold at all (it would need one added). Strategy (A) is
local to the workflow body, doesn't drag interceptor changes into a
test-hygiene plan, and produces a stricter assertion (the flag is
captured exactly where the contested `yield` used to live).

**Note on `instanceof ScopeContext`.** `Workflow::getCurrentContext()`'s
declared return type is `WorkflowContextInterface`, and `isFiberMode()` is
only defined on `ScopeContext`. The `instanceof` guard keeps the test
compatible with any other context implementation that might be wired in
later — and if the guard ever evaluates false, the test fails with a
clear sentinel-mismatch instead of a `BadMethodCallException`. Importing
`Temporal\Internal\Workflow\ScopeContext` from a test is acceptable here:
test code routinely reaches into `Temporal\Internal\*` for assertion
purposes and there is no public surface that exposes Fiber mode otherwise
(M11 / open question 3 in the review track this).

## Tasks

### Phase 1 — Fix `RawValueTest.php`

- [x] **Task 1** — Remove `yield` from `FeatureWorkflow::run` and add the Fiber-mode sentinel.
      File: `tests/Acceptance/Extra/DataConverter/Fibers/RawValueTest.php`
      Changes:
      1. Add `use Temporal\Internal\Workflow\ScopeContext;` to the file's import block.
      2. In `FeatureWorkflow::run` (currently lines ~38–50):
         - Replace `return yield $activity->bypass($rawValue);` with a
           plain call sequence: `$result = $activity->bypass($rawValue);`
           (the `FiberProxy` auto-suspends inside the Fiber).
         - Capture the sentinel:
           ```php
           $context = Workflow::getCurrentContext();
           $fiberMode = $context instanceof ScopeContext && $context->isFiberMode();
           ```
         - Return a shape carrying both:
           `return ['rawValue' => $result, 'fiberMode' => $fiberMode];`.
         - Do **not** declare a `\Generator` return type. Leave the
           method's declared return type absent (matches the non-Fiber
           sibling `tests/Acceptance/Extra/DataConverter/RawValueTest.php`)
           or narrow to `: array` — but **never** `\Generator`.
      3. In `RawValueTest::check`:
         - Change `$result = $stub->getResult(RawValue::class);` to
           `$result = $stub->getResult('array');` so the workflow
           returns the new shape.
         - Replace the three existing asserts with four:
           ```php
           self::assertInstanceOf(RawValue::class, $result['rawValue']);
           self::assertInstanceOf(Payload::class, $result['rawValue']->getPayload());
           self::assertSame('hello world', $result['rawValue']->getPayload()->getData());
           self::assertTrue($result['fiberMode'], 'Workflow body did not run inside a Fiber');
           ```
      Acceptance:
      - `grep -n 'yield\|Generator' tests/Acceptance/Extra/DataConverter/Fibers/RawValueTest.php` returns no lines.
      - `composer cs:diff` clean on the file.
      - The file still respects CLAUDE.md "no comments" — no new comments
        added; no essay docblocks.

### Phase 2 — Fix `ContextTest.php` — workflow body

- [x] **Task 2** — Remove `yield` from `TestWorkflow::handle` and add the Fiber-mode sentinel.
      File: `tests/Acceptance/Extra/Interceptors/Fibers/ContextTest.php`
      Changes:
      1. Add `use Temporal\Internal\Workflow\ScopeContext;` to imports.
      2. In `TestWorkflow::handle` (currently lines ~104–118):
         - Replace
           `$activityClass = yield Workflow::executeActivity(…)` with the
           non-yielded form. `Workflow::executeActivity` on the Fiber
           facade auto-awaits internally (it's an "await" method per the
           facade parity table in the review), so the body becomes:
           ```php
           $activityClass = Workflow::executeActivity(
               'Extra_Interceptors_Fibers_Context.handler',
               ['foo'],
               Activity\ActivityOptions::new()->withScheduleToCloseTimeout('10 seconds'),
           );
           ```
         - Replace `yield Workflow::await(fn() => $this->exit);` with the
           non-yielded form: `Workflow::await(fn() => $this->exit);`.
         - Capture the sentinel immediately before the `return` statement:
           ```php
           $context = Workflow::getCurrentContext();
           $fiberMode = $context instanceof ScopeContext && $context->isFiberMode();
           ```
         - Extend the returned array with `'fiberMode' => $fiberMode`:
           ```php
           return [
               'activity' => $activityClass,
               'workflow' => $class,
               'assert' => Workflow::getInstance() === $this,
               'fiberMode' => $fiberMode,
           ];
           ```
         - Do **not** declare a `\Generator` return type on `handle`.
      3. In `ContextTest::instanceInContext`, add a fourth assertion:
         ```php
         self::assertTrue(
             $result['fiberMode'],
             'Workflow body did not run inside a Fiber',
         );
         ```
      Acceptance:
      - `grep -n 'yield\|Generator' tests/Acceptance/Extra/Interceptors/Fibers/ContextTest.php` returns no lines.
      - `instanceInContext` still asserts the original three things
        (activity class, workflow class, instance-equality) **and** the
        new sentinel.

- [x] **Task 3** — Verify other workflows in `ContextTest.php` don't regress.
      Same file. `TestFailingWorkflow::handle` and
      `TestReadonlyConstructorWorkflow::handle` already have no `yield`.
      Confirm they remain Generator-free after the Phase 2 edits. No
      code change expected; this is a read-only verification step.
      Acceptance: a final `grep -n 'yield\|Generator'` against the file
      returns zero hits.

### Phase 3 — Fix `ContextTest.php` — exception assertion (M12 fold-in)

- [x] **Task 4** — Replace `assertStringContainsString` with a full-string equality assertion on line ~55.
      File: `tests/Acceptance/Extra/Interceptors/Fibers/ContextTest.php`
      Context: the `failInConstructor` test currently does:
      ```php
      try {
          $stub->getResult('array');
          $this->fail('An exception should have been thrown.');
      } catch (WorkflowFailedException $e) {
          $prev = $e->getPrevious();
          self::assertInstanceOf(ApplicationFailure::class, $prev);
          self::assertStringContainsString('constructor', $prev->getOriginalMessage());
      }
      ```
      The exception is constructed deterministically in
      `TestFailingWorkflow::__construct` as
      `throw new ApplicationFailure('constructor', 'error', true)`.
      `ApplicationFailure::getOriginalMessage()` returns exactly
      `'constructor'`. Upgrade to a full-string assertion:
      ```php
      self::assertSame('constructor', $prev->getOriginalMessage());
      ```
      Use `assertSame`, not `expectExceptionMessage()`, because the
      assertion lives in a `catch` block on `$prev` (the nested cause),
      not on the outer `WorkflowFailedException` — `expectException*` APIs
      target the outermost thrown exception, not a nested cause. This is
      consistent with CLAUDE.md's "Exception tests: assert full messages,
      not fragments" rule, which uses `expectExceptionMessage()` as its
      example but allows `assertSame` on the caught instance when the
      message under test belongs to a `getPrevious()` chain.
      Acceptance:
      - Line 55 (post-Phase-2 line shift accounted for) reads
        `self::assertSame('constructor', $prev->getOriginalMessage());`.
      - No other `assertStringContains*` calls are introduced.
      - `failInInterceptorExecute` (line ~69 pre-edit) is **left
        untouched** — out-of-scope per the "Out-of-scope" section.

### Phase 4 — Verification

- [ ] **Task 5** — Static checks.
      Run, in order, from the repo root:
      ```
      composer cs:diff
      composer psalm
      ```
      `cs:diff` must be clean on the two edited files. `psalm` must not
      introduce new errors against the baseline; if it does, the
      sentinel import (`use Temporal\Internal\Workflow\ScopeContext;`)
      is the most likely culprit — `ScopeContext` is internal but Psalm
      at level 2 should accept it for `instanceof`.

- [ ] **Task 6** — Run the two affected acceptance tests.
      The `Acceptance-Fast` suite covers `Acceptance/Extra/**` by default.
      Temporal server + RoadRunner must be running locally
      (`TEMPORAL_ADDRESS=127.0.0.1:7233`). From the repo root:
      ```
      composer test:accept-fast
      ```
      If the fast suite is too broad to iterate on quickly, narrow with
      a direct PHPUnit invocation via the project's runner script:
      ```
      tests/runner.php vendor/bin/phpunit \
          --testsuite="Acceptance-Fast" \
          --filter='Extra_DataConverter_Fibers_RawValue|Extra_Interceptors_Fibers_Context'
      ```
      Or by file path:
      ```
      tests/runner.php vendor/bin/phpunit \
          --testsuite="Acceptance-Fast" \
          tests/Acceptance/Extra/DataConverter/Fibers/RawValueTest.php \
          tests/Acceptance/Extra/Interceptors/Fibers/ContextTest.php
      ```
      Acceptance:
      - `RawValueTest::check` passes; the new `fiberMode` assertion is `true`.
      - `ContextTest::instanceInContext` passes; the new `fiberMode`
        assertion is `true`.
      - `ContextTest::failInConstructor` passes with the full-string
        assertion in place.
      - `ContextTest::failInInterceptorExecute` and
        `readonlyContextInConstructor` unchanged and still pass.
      - No regression elsewhere in `Acceptance-Fast`.

- [ ] **Task 7** — Negative-control sanity check (manual, **not committed**).
      To prove the sentinel actually catches the regression that motivated
      this plan, the implementer should — locally and uncommitted —
      re-introduce a single `yield` into one of the rewritten workflow
      bodies (e.g. `return yield ...` in `FeatureWorkflow::run`) and
      re-run the filtered acceptance test from Task 6. The `fiberMode`
      assertion **must** fail. If it doesn't, the sentinel is wired
      wrong (e.g. `ScopeContext` is being mistakenly recognized in the
      Generator path too) and Phase 1/2 needs revisiting before this
      plan can be considered done.
      Revert the local `yield` insertion before committing.
      Acceptance: implementer records in the commit message (or PR body)
      that the negative control was performed and the sentinel
      assertion failed under the reverted state.

## Validation gates (re-check before commit)

- [ ] `grep -n 'yield\|Generator' tests/Acceptance/Extra/DataConverter/Fibers/RawValueTest.php` is empty.
- [ ] `grep -n 'yield\|Generator' tests/Acceptance/Extra/Interceptors/Fibers/ContextTest.php` is empty.
- [ ] Both files contain exactly one new `assertTrue($result['fiberMode'], …)`.
- [ ] `ContextTest.php` line for `failInConstructor`'s nested-cause
      assertion reads `self::assertSame('constructor', $prev->getOriginalMessage());`.
      Line number will have shifted relative to the original 55 after
      the Phase 2 body edits — the implementer must confirm by reading
      the file, not by trusting the original line number.
- [ ] `ContextTest.php` still contains exactly one `assertStringContainsString`
      call (the line ~69 occurrence in `failInInterceptorExecute`, which
      this plan deliberately leaves for the M12 plan).
- [ ] `composer cs:diff` clean.
- [ ] `composer psalm` no new errors.
- [ ] `composer test:accept-fast` green (or filtered run as above).
- [ ] No new comments added to either file (CLAUDE.md hard rule).
- [ ] Negative-control (Task 7) performed and recorded.

## Risk / failure modes

1. **`getCurrentContext()` returns something that is not `ScopeContext`.**
   The sentinel evaluates to `false` and the test fails loudly. That's
   the correct behavior — it signals exactly the kind of refactor where
   the Fiber bridge stopped using `ScopeContext` and needs investigation.
   The failure is informative, not a false positive.

2. **Future change makes `Workflow::getCurrentContext()` throw "outside
   workflow context".** It already throws in that case today, before any
   yield-vs-fiber concern, so this is a pre-existing condition shared
   with every other Fiber test. No mitigation needed.

3. **`Workflow::executeActivity` on the Fiber facade silently returns a
   `PromiseInterface` instead of awaiting (regression case).** The
   facade parity table in `.ai-factory/research/fibers-review.md` lists
   `executeActivity` as auto-awaited. If that ever ceases to be true,
   `$activityClass` would be a Promise object and the existing
   `assertSame(TestActivity::class, $result['activity'])` would fail
   with a clear type mismatch — providing defense-in-depth on top of
   the primary sentinel. No mitigation required, but worth being aware
   of as a second-line signal.

4. **`ScopeContext` becomes non-cloneable / `isFiberMode()` reads wrong
   from a cloned context.** Query handlers aren't in scope here (the
   sentinel is read from the main workflow handler, not from a query),
   so C7's incidental-correctness concern doesn't apply.

5. **`composer test:accept-fast` requires Temporal server + RoadRunner
   binaries.** If they're missing, run `composer get:binaries` first and
   make sure `tests/Acceptance/bootstrap.php` is finding `TEMPORAL_ADDRESS`.
   This is an environmental gate, not a plan failure.

## Done definition

- Both files compile.
- Both files contain no `yield` and no `\Generator` return type.
- Both files have a Fiber-mode sentinel that asserts `true` in the
  passing case.
- The M12 line 55 fragment-vs-full-message issue is fixed (line 55 was
  original; actual line number after edits may shift — the assertion
  is what matters, not the line).
- `composer test:accept-fast` is green on the two affected tests.
- The negative-control sanity check (Task 7) has been performed at
  least once during implementation and recorded in the commit message
  or PR body.

