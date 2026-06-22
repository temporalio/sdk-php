# Fibers Branch — Consolidated Code Review

Updated: 2026-05-23
Status: review-complete, action plan pending
Branch: `fibers`
Reviewed by: 5 parallel review agents (A1 bridge / A2 facade / A3 stubs / A4 cross-scope / A5 tests)

---

## Executive Summary

The Fiber-based experimental API is **structurally sound** — the Fiber↔Generator bridge in
`Scope::createFiberHandler` is a clever way to keep the existing Generator-driven `Scope::next()`
loop untouched. Per-scope `ScopeContext::$fiberMode` correctly isolates Fiber state between
parent/child/signal/update scopes (the largest worry up front — that signal handlers share the
main scope's `fiberMode` — turned out to be unfounded; signals get freshly-created child scopes
in `Process.php:130-166`).

However the branch ships with **multiple critical issues** that point to insufficient review
before merge:

- Debug code left in `tests/Acceptance/worker.php` (`$a=1;`)
- Two "Fiber" tests that don't actually run inside a Fiber (use `yield`, which forces
  Generator semantics — the bridge is bypassed)
- Two pairs of tests register **the same workflow name** as their non-Fiber counterparts on
  the same task queue — guaranteed registration collision
- Two whole test files (`Schedule/Fibers/*`) are byte-identical to the non-Fiber versions
- Several test files don't import the Fiber facade at all but live under `Fibers/`
- **No regression test** for the `setFiberMode` correctness commit (`5be5c6ac`)
- **No unit tests at all** for `src/Experiments/Fibers/*` (8 files, 0 unit tests)

On the runtime side the most serious finding is a **defensive gap in the query handler path**:
`Process::setQueryExecutor` clones the scope's `ScopeContext` but doesn't force
`setFiberMode(false)`, leaving correctness incidentally dependent on the clone happening between
Fiber runs.

Below: 8 CRITICAL, 11 HIGH, 14 MEDIUM, 9 LOW, plus open questions.

---

## CRITICAL

### C1 — Debug code committed in test bootstrap
**Where:** `tests/Acceptance/worker.php:128` (added by branch — `git diff master..HEAD`)
**Issue:** Trailing `$a=1;` is shipped. Functionally harmless but signals the branch
wasn't reviewed before commit. The same hunk also splits
`$container->get(WorkerFactoryInterface::class)->run();` into two statements with no
stated reason.
**Fix:** Delete the debug line; revert or justify the split.
**Suggested test:** None — purely a hygiene fix.

### C2 — Two "Fiber" workflows use `yield`, silently bypassing the Fiber path
**Where:**
- `tests/Acceptance/Extra/DataConverter/Fibers/RawValueTest.php:49` — `return yield $activity->bypass($rawValue);`
- `tests/Acceptance/Extra/Interceptors/Fibers/ContextTest.php:107, 112` — `yield Workflow::executeActivity(...)`

**Issue:** PHP turns any function containing `yield` into a Generator. These workflow
methods therefore **never run inside a Fiber**; `Scope::createFiberHandler` wraps the
Fiber, the Fiber body returns the Generator without iterating, the bridge sees
`isTerminated()=true`, and the Generator is iterated by the legacy path. The tests claim
Fiber coverage and provide none.

Worse for `RawValueTest`: `$activity` is a `FiberProxy`, so `$activity->bypass(...)` returns
the resolved value (not a promise). `yield <scalar>` produces undefined runtime behavior.
That the test passes is incidental.

**Fix:** Remove all `yield`/`\Generator` and rewrite as plain Fiber bodies.
**Suggested tests:** After fix, add a sentinel assertion proving the body executed inside a
Fiber (e.g. via a side-channel reading `Facade::getCurrentContext()->isFiberMode()` from
the workflow body, or by registering an interceptor that captures the mode at handler
entry).

### C3 — Duplicate `Logger_Test_Workflow` registration across Fiber and non-Fiber tests
**Where:**
- `tests/Acceptance/Extra/Workflow/Fibers/LoggerTest.php:207` registers `Logger_Test_Workflow`
- `tests/Acceptance/Extra/Workflow/LoggerTest.php:207` registers same name on same `default` queue

**Issue:** Either the worker fails to boot with "Workflow with name … already registered",
or whichever `RuntimeBuilder` discovers last silently wins and the other test exercises the
wrong workflow class. Either way the Fiber test provides zero independent coverage.
**Fix:** Rename Fiber version to `Logger_Test_Fibers_Workflow` (or similar) and update the
`#[Stub]` attribute.
**Suggested test:** A meta-test that walks all registered workflow classes on boot and
asserts uniqueness of `#[WorkflowMethod(name:)]`.

### C4 — `TaskQueue/Fibers/WorkflowATest` and `WorkflowBTest` are not Fiber tests at all
**Where:**
- `tests/Acceptance/Extra/TaskQueue/Fibers/WorkflowATest.php` — no import of `Experiments\Fibers\Workflow`; body is `return 42;`
- `tests/Acceptance/Extra/TaskQueue/Fibers/WorkflowBTest.php` — imports `Temporal\Workflow` (base namespace)
- Both register the bare name `"Workflow"` on the `default` queue — same name as the non-Fibers TaskQueue tests

**Issue:** Zero Fiber coverage AND guaranteed duplicate-registration collision. The
non-Fibers TaskQueue tests are in `TaskQueueResolver::SHARED_QUEUE_EXCLUSIONS` and get
their own queue; the Fibers versions aren't.
**Fix:** Either delete or rewrite. If kept, rename workflows + add to
`SHARED_QUEUE_EXCLUSIONS`.

### C5 — `Schedule/Fibers/ScheduleClientTest.php` and `ScheduleUpdateTest.php` are byte-identical copies
**Where:** both files; `diff` against `tests/Acceptance/Extra/Schedule/{ScheduleClientTest,ScheduleUpdateTest}.php` shows only a 1-line namespace difference.
**Issue:** Schedule operations are client-side; nothing touches `Experiments\Fibers\*`.
Pure noise that doubles runtime.
**Fix:** Delete.

### C6 — `setFiberMode` never reset when the bridge Generator is GC'd without being driven to completion
**Where:** `src/Internal/Workflow/Process/Scope.php:501-516` (bridge `finally`) ×
`Scope::destroy():299-312` and `Scope::cancel():207-225`
**Issue:** Commit `5be5c6ac` fixed the `$fiber->start()`-throws path. The remaining hole:
`Scope::destroy()` unsets `$this->scopeContext` (line 302 first destroys, line 303 then
unsets) **before** unsetting `$this->coroutine`. If the bridge Generator's `finally` runs
later during GC, it calls `setFiberMode(false)` on a context whose underlying state has
already been touched by `parent::destroy()`. `Scope::cancel()` doesn't reset `fiberMode`
either — for detached scopes, the early-return at line 209-212 skips `onCancel` entirely.

Practical consequence: a Fiber-mode detached scope torn down by a non-`DestructMemorizedInstanceException`
reason can leave `fiberMode=true` on a soon-to-be-destroyed `ScopeContext`. Any surviving
reference + a subsequent `FiberHelper::await()` against it will call `\Fiber::suspend()`
outside any Fiber → fatal `FiberError`.

**Fix:** In `Scope::destroy()` and `Scope::cancel()`, explicitly
`$this->scopeContext->setFiberMode(false)` before relinquishing references; consider also
throwing `DestructMemorizedInstanceException` into the bridge so user `finally` blocks run
(see also M9 — Fiber `finally` blocks don't run on hard destroy).
**Suggested test:** A unit test that:
1. Starts a Fiber-mode scope with a never-resolving promise (suspends the Fiber).
2. Calls `Scope::destroy()` directly.
3. Asserts `ScopeContext::isFiberMode() === false` afterward.

### C7 — Query handler doesn't force `fiberMode=false` on the cloned scope context
**Where:** `src/Internal/Workflow/Process/Process.php:54-71`
**Issue:** Query path does `$context = $this->scopeContext->withInput(new Input(...))`,
which is `clone`. `fiberMode` is copied by value. Today the main scope's flag is `false`
**between** Fiber runs (cleared by the bridge `finally`), so cloning at a query boundary
produces `false`. This is **incidental** correctness: any change that keeps `fiberMode=true`
longer (e.g. C6 fix done wrong, or a future direct-DeferredFiber wiring) makes queries
crash with `FiberError: Cannot suspend outside of a fiber` the moment the query handler
calls a `Fibers\Workflow::*` method.
**Fix:** Add `$context->setFiberMode(false);` right after `setReadonly(true)` in
`Process.php`'s query executor.
**Suggested test:** Acceptance test — Fiber-mode workflow that has a query handler that
calls `Fibers\Workflow::getInfo()` (sync) or, for the negative case, expects an error if
it tries `executeActivity`. Plus a unit test that flips `fiberMode=true` on a context,
clones via `withInput`, and asserts the clone was reset.

### C8 — Bare `catch (\Throwable)` at `Scope::next:422-425` swallows workflow exceptions into `onResult(null)`
**Where:** `src/Internal/Workflow/Process/Scope.php:417-425`
**Issue:** When the coroutine throws (e.g. uncaught exception inside the Fiber bubbles
out of the bridge Generator through `DeferredGenerator`'s `handleException` rethrow),
the `try { if (!$this->coroutine->isRunning()) { $this->onResult($this->coroutine->getReturn()); } } catch (\Throwable) { $this->onResult(null); return; }`
silently resolves the workflow to **null**. Workflow that should have failed reports
success-with-null-result.

This isn't strictly Fiber-introduced (the same shape exists in master) but the Fiber
bridge makes it easier to trigger because `$fiber->getReturn()` raises `FiberError` if
the Fiber terminated with an exception — that path now flows through the bare catch.

**Fix:** Replace the bare catch with `catch (\Throwable $e) { $this->onException($e); }`.
**Suggested test:** Fiber workflow that throws `RuntimeException` synchronously inside an
activity callback chain after first suspension. Assert workflow stub fails with the
expected exception, not resolves to null.

---

## HIGH

### H1 — `awaitWithTimeout` rejects `Experiments\Fibers\Mutex`
**Where:** `src/Experiments/Fibers/Workflow.php:217`
**Issue:** Signature is `callable|BaseMutex|PromiseInterface ...$conditions` — missing
`Mutex` from the union. Sibling `Workflow::await(...)` accepts it (line 209).
Passing a `Fibers\Mutex` to `awaitWithTimeout` causes `TypeError`.
**Fix:** Add `Mutex` to the union.
**Suggested test:** `awaitWithTimeout('10s', new \Temporal\Experiments\Fibers\Mutex())`
inside a Fiber workflow — must not TypeError.

### H2 — `sideEffect` drops `SideEffectOptions` parameter
**Where:** `src/Experiments/Fibers/Workflow.php:231`
**Issue:** Fiber facade: `sideEffect(callable $value): mixed`. Base:
`sideEffect(callable $value, ?SideEffectOptions $options = null)`. Migration breaks any
call site that passed options.
**Fix:** Add `?SideEffectOptions $options = null` and forward.
**Suggested test:** Acceptance test that calls `Fibers\Workflow::sideEffect($cb, $opts)`
and asserts options were applied (e.g. via the options' visible effect on result).

### H3 — `FiberProxy` returns concrete decorator, breaking `@return T` and IDE/Psalm inference
**Where:** `src/Experiments/Fibers/FiberProxy.php:19` × `Workflow.php:302-356`
**Issue:** Factories declare `@return T`, return `new FiberProxy(...)`. No `@template`,
no `@mixin T`. IDE autocompletion and static analysis lose the `T` shape; calls go
through `__call` at runtime so it works, but `$activity->myMethod(...)` is unknown to
analyzers.
**Fix:** Add `@template T of object` + `@mixin T` to `FiberProxy`. Same for the typed
decorator stubs.
**Suggested test:** Psalm/PHPStan run against `Experiments\Fibers\` namespace at strict
level — assert zero unresolved-method errors.

### H4 — Typed decorator stubs don't implement their interfaces
**Where:** `FiberActivityStub`, `FiberChildWorkflowStub`, `FiberExternalWorkflowStub`
**Issue:** Decorators redefine method return types (e.g. `execute(): mixed` instead of
`PromiseInterface`) so they cannot `implements ActivityStubInterface`. Functions typed
against the canonical interfaces can't accept the Fiber-mode stubs.
**Fix:** Declare parallel `Experiments\Fibers\*StubInterface` types and have the
decorators implement them. Optionally expose `inner()` escape hatches.
**Suggested test:** Type-system test: a function `f(FiberActivityStubInterface $s)` and
its non-fiber counterpart `g(ActivityStubInterface $s)` both compile against the right
decorator.

### H5 — No regression test for the `setFiberMode` correctness commit (`5be5c6ac`)
**Where:** missing — commit touched only `src/Internal/Workflow/Process/Scope.php`
**Issue:** The commit moves `setFiberMode(false)` into `try/finally` blocks. Without a
test, a future revert (or a related refactor) silently regresses.
**Fix:** Add tests that trigger:
1. `$fiber->start()` throwing synchronously — assert `isFiberMode()==false` after.
2. Bridge generator exiting via uncaught Fiber exception — assert reset.
3. Bridge generator GC'd while suspended (combined with C6 fix) — assert reset.

### H6 — `MutexYieldTest` constructor calls `Mutex::lock()` outside a Fiber — undocumented behavior
**Where:** `tests/Acceptance/Extra/Workflow/Fibers/MutexYieldTest.php:67-69`
**Issue:** Constructor runs before `Scope::createFiberHandler` wraps the workflow method.
`FiberHelper::await` sees `isFiberMode()==false`, returns the (already-resolved) promise,
discards it. Base `Mutex::lock()` synchronously sets `locked=true` for the first call,
so this happens to work. A second consecutive `lock()` would leak a Deferred and hang
the workflow. Behavior is undocumented and fragile.
**Fix:** Either explicitly document the pre-Fiber lock contract, or move the `lock()`
call out of the constructor.
**Suggested test:** Unit test on `Fibers\Mutex` asserting that first-call `lock()`
outside a Fiber returns a resolved promise; second-call `lock()` either throws or
returns a pending promise the caller can't await (the latter is the leak case).

### H7 — `gather` not cancellable, signature lies about return type
**Where:** `src/Experiments/Fibers/Workflow.php:406-411`
**Issue:** Declared `: mixed`, returns `Promise::all($scopes)` (an array after await).
Caller has no handle to cancel individual scopes — a timeout watchdog around `gather`
can't stop in-flight scopes. PHPDoc says `array<mixed>` but the declared return is
`mixed`.
**Fix:** Tighten return type to `array`. Either document fire-and-forget semantics or
expose the scopes (`gatherWithScopes()` variant).
**Suggested test:** `gather(fn() => sleep, fn() => sleep)`; cancel surrounding scope;
assert both inner scopes were cancelled (currently would not be).

### H8 — `runLocked` doesn't await Promise returns from the callable
**Where:** `src/Experiments/Fibers/Workflow.php:377-392`
**Issue:** Body has `return $callable()`. Base `runLocked` was `yield $callable()` which
unwrapped a Generator/Promise. Fiber-mode `$callable()` is purely synchronous. If a
user returns a raw `PromiseInterface` (e.g. they accidentally use `createTimer` instead
of `timer`), the surrounding scope resolves to the promise itself.
**Fix:** Wrap the return in `FiberHelper::await($callable())` when the result is a
promise; OR document that callables must use the fiber facade (which auto-awaits)
exclusively.
**Suggested test:** `runLocked($m, fn() => Fibers\Workflow::createTimer(1))` — assert
the surrounding scope resolves to null (post-timer), not to a PromiseInterface.

### H9 — `Scope.php:611` short-circuit-side-effect violates the project's own style rule
**Where:** `src/Internal/Workflow/Process/Scope.php:611`
**Issue:** `$this->services->queue->count() === 0 and $this->services->loop->tick();`
is exactly the pattern banned in CLAUDE.md `### Control flow`. Pre-existing, not
Fiber-introduced, but the file is in scope on this branch.
**Fix:** Convert to `if`.

### H10 — Most "Fiber" tests are 1-to-1 copies with `yield` removed, none stress nested suspension chains
**Where:** all of `tests/Acceptance/Extra/**/Fibers/**`
**Issue:** Coverage equivalence is shallow. No test does multi-step Fiber suspension
inside nested function calls (e.g. workflow → helper method A → helper method B →
`executeActivity` → resume back up the stack). The 8-finding Fiber lifecycle (A1) is
exercised at a single level only.
**Fix:** Add at least one acceptance test with a 3+ deep call stack culminating in an
`executeActivity` call, then unwinding through `try/finally` blocks (also exercises M9).
**Suggested test:** "Deep call stack fiber workflow" with: outer `try/finally`, nested
helper calls 3 levels deep, suspend on activity, throw, catch, suspend again, complete.

### H11 — Tests poll on raw `historyLength` math (flaky against upstream event-count changes)
**Where:** `tests/Acceptance/Extra/Workflow/Fibers/MutexYieldTest.php:23-35` and others
**Issue:** `historyLength >= 4 + initial` is brittle to any future upstream change in
event-count semantics (heartbeats, markers).
**Fix:** Poll on a semantic indicator (a query'd state flag, a signal-loopback).
**Suggested test:** Refactor existing tests to a semantic-wait helper.

---

## MEDIUM

### M1 — Bridge generator yields stale value after `$fiber->throw` re-suspends with same promise
**Where:** `src/Internal/Workflow/Process/Scope.php:508-510`
**Issue:** If user code catches injected exception and re-awaits the **same already-settled**
promise, `Scope::nextPromise` attaches handlers to a resolved promise, fires
synchronously, calls `coroutine->send/throw` reentrantly.
**Fix:** Add `if ($fiber->isTerminated()) break;` after the inner `throw`; document
re-await of settled promises as undefined.

### M2 — `DeferredFiber.php` is dead code
**Where:** `src/Experiments/Fibers/DeferredFiber.php` + misleading docblock at
`CoroutineInterface.php:17`
**Issue:** Never instantiated outside its own file. Implements `CoroutineInterface` but
the bridge always uses Generator wrapping. The interface docblock claims both impls are
wired.
**Fix:** Delete the file and update the docblock, OR wire it up with tests.

### M3 — `createCoroutine` wraps **every** workflow handler in a Fiber, even Generator-style
**Where:** `src/Internal/Workflow/Process/Scope.php:470-518`
**Issue:** Generator workflows now incur `new \Fiber()`, `start()`, `getReturn()` for no
benefit. Per-workflow this is small; with `async()` in tight loops (or `gather`/heavy
fan-out), it adds up.
**Fix:** Detect Generator-returning handlers (via reflection or a registration flag) and
skip the Fiber wrap.

### M4 — `\Temporal\Workflow::setCurrentContext($scopeContext)` inside Fiber body looks redundant
**Where:** `src/Internal/Workflow/Process/Scope.php:484`
**Issue:** `Scope::start` → `next()` → `makeCurrent()` already set the context before the
coroutine is driven. Line 484 sets it again from inside the Fiber's first execution.
Likely dead/paranoia code.
**Fix:** Trace whether removing it changes behavior; if not, delete.

### M5 — `Workflow::async`/`asyncDetached`/`runLocked`/`gather` PHPDocs narrow `callable(): T` and drop the Generator union
**Where:** `Workflow.php:188, 198, 377, 406`
**Issue:** Generators still work at runtime (`Scope::next:448` attaches them as sub-scopes),
but the type aliases mislead users.
**Fix:** Either widen the PHPDoc to match base, or document that Generators are illegal
in Fiber mode (and reject at runtime).

### M6 — `gather`/`runLocked` callables silently accept Generators with confusing outcomes
**Where:** `Workflow.php:377, 406`
**Issue:** If user returns a `\Generator` from the callable, `Scope::next` spawns a
sub-scope but the outer scope's value is `Generator`, not the inner result. Confusing
without a runtime error.
**Fix:** Detect and reject, or align with base semantics.

### M7 — `FiberActivityStub::createExecution()` aliases `execute()` instead of starting+returning
**Where:** `src/Experiments/Fibers/FiberActivityStub.php:46-48`
**Issue:** Name implies "schedule, return handle". Body actually calls `inner->execute(...)`.
Same return type, same effect — except the outer decorator does NOT auto-await. Real
purpose: escape hatch for `awaitWithTimeout($run, ...)` composition. Name hides this.
**Fix:** Rename to `executeAsync()` or `executeRaw()`. Mirror the hatch on child / external
decorators (currently absent).

### M8 — `FiberChildWorkflowStub::signal` returns `mixed`, awaits a `PromiseInterface<void>`
**Where:** `src/Experiments/Fibers/FiberChildWorkflowStub.php:71-74`
**Issue:** `mixed` for what is always null. Same on `FiberExternalWorkflowStub::signal`/`cancel`.
**Fix:** Declare `: void`; drop the implicit return.

### M9 — Fiber `finally` blocks don't run on hard `Scope::destroy()`
**Where:** Scope.php destroy/cancel × user-side `try/finally` in workflow bodies
**Issue:** PHP destructs a suspended Fiber without running its in-flight `finally` blocks.
Differs from Generator `finally` (PHP runs on GC) and Go `defer` (always runs).
**Fix:** On `Scope::destroy()`, throw a `DestructMemorizedInstanceException` through the
bridge to give cleanup a chance before unsetting refs.
**Suggested test:** Workflow with `try { await(activity) } finally { sideEffect(cleanup) }`;
force `Scope::destroy()` while suspended; assert cleanup ran.

### M10 — `FiberHelper::await` doesn't validate suspension value type
**Where:** `src/Experiments/Fibers/FiberHelper.php:28-39` + `Scope.php:430-459`
**Issue:** Direct `\Fiber::suspend($nonPromise)` from user code routes the value through
`Scope::next()`'s switch in unexpected ways. The `default` branch resumes the Fiber with
the same value, masking misuse.
**Fix:** Add assert / type guard in the bridge.

### M11 — `FiberHelper::await` silently degrades outside fiber-mode contexts
**Where:** `FiberHelper.php:32-38`
**Issue:** Returns the raw `PromiseInterface` when `$context` is not a `ScopeContext` or
`fiberMode==false`. Callers expecting `: void` or `mixed` will silently get a Promise.
Most facade methods pre-throw via `Workflow::getCurrentContext()` so unreachable; but
`Mutex::lock()` (constructor case, H6) and `Promise::all([])` are reachable.
**Fix:** Throw `OutOfContextException` (or document the degradation).

### M12 — Exception assertions use `assertStringContainsString` / `expectExceptionMessageMatches` instead of full-message asserts
**Where:**
- `tests/Acceptance/Extra/Update/Fibers/UntypedStubTest.php:100-104`
- `tests/Acceptance/Extra/Update/Fibers/UpdateWithStartTest.php:61`
- `tests/Acceptance/Extra/Interceptors/Fibers/ContextTest.php:55`

**Issue:** Violates CLAUDE.md "Exception tests: assert full messages, not fragments".
**Fix:** Use `expectExceptionMessage()` with full canonical strings.

### M13 — `runLocked`'s `BaseMutex` branch never exercised
**Where:** `tests/Acceptance/Extra/Workflow/Fibers/MutexRunLockedTest.php`
**Issue:** Every test passes a `Fibers\Mutex`; the `$mutex instanceof Mutex` else branch
(`FiberHelper::await($mutex->lock())`) is dead in tests.
**Fix:** Add a test calling `runLocked` with a base `\Temporal\Workflow\Mutex`.

### M14 — No unit tests anywhere for `src/Experiments/Fibers/*`
**Where:** `find tests/Unit -path "*Fiber*"` returns zero results
**Issue:** All 8 files in `Experiments\Fibers` lack isolated unit tests. End-to-end
acceptance tests don't cover branches like `FiberHelper::await` no-context fall-through,
`Mutex::tryLock`/`getInner`, `DeferredFiber::throw`/`catch`/`send`, `FiberProxy::__call`
non-Promise returns, `Promise::race`/`map`/`reduce`.
**Fix:** Add a `tests/Unit/Experiments/Fibers/` suite covering each class.

---

## LOW

### L1 — `Scope::cancel` skips `onCancel` for detached scopes — leaves Fiber+bridge suspended
**Where:** `Scope.php:207-212`
**Issue:** Documented behavior ("detached scopes can be offloaded via memory flush"), but
combined with C6 the cleanup is non-obvious.
**Fix:** Document explicitly + cross-link to memory flush path.

### L2 — `// todo ->context or ->scopeContext?` left in code
**Where:** `Scope.php:443`
**Issue:** TODO without issue link — banned by CLAUDE.md `### Comments`.
**Fix:** Resolve the question and delete.

### L3 — `Workflow::createTimer` vs `Workflow::timer` naming confusion
**Where:** `Workflow.php:239-250`
**Issue:** Two semantically distinct operations with similar names. No analogous
`createActivity`/`createChildWorkflow`. Users will guess wrong.
**Fix:** Rename raw variant `timerPromise` / `rawTimer` or remove.

### L4 — Type-narrowing loses generics from base facade (`executeActivity` etc. all `mixed`)
**Where:** `Workflow.php:209, 217, 222, 231, 239, 256, 265, 274, 280, 285, 290`
**Issue:** Base methods are generic on `$returnType`; Fiber facade widens to `mixed`.
**Fix:** Add `@template T` PHPDoc per awaiting method.

### L5 — `ActivityMethodTest` methods not marked `#[Test]`
**Where:** `tests/Acceptance/Extra/Activity/Fibers/ActivityMethodTest.php:21, 30, 47`
**Issue:** Style inconsistency vs surrounding Fiber tests.
**Fix:** Add `#[Test]`.

### L6 — `ChildWorkflowIdTest` tight-loop poll with no sleep
**Where:** `tests/Acceptance/Extra/Workflow/Fibers/ChildWorkflowIdTest.php:25-31`
**Issue:** Hammers the server. Race-condition flakes hide real bugs.
**Fix:** Add `usleep` or wait on semantic signal.

### L7 — `phpunit.xml.dist` slow-suite excludes are manual per Fiber test
**Where:** added by commit `4a1e928a`
**Issue:** No directory-level filter; easy to forget.
**Fix:** Consider `<directory>tests/Acceptance/Extra/*/Fibers</directory>` block.

### L8 — Commit messages uninformative ("test: more tests", "test: correct deferred")
**Where:** `git log master..HEAD`
**Issue:** Three "test: correct deferred" commits touch `src/Internal/Workflow/Process/Scope.php`
and `src/Workflow/WorkflowExecutionInfo.php` (production code, not tests). The `setFiberMode`
fix lives in a separate commit `5be5c6ac`. Why-info missing.
**Fix:** Process feedback for future PRs.

### L9 — `FiberProxy::__call` returns inner result as-is when non-Promise
**Where:** `src/Experiments/Fibers/FiberProxy.php:25-34`
**Issue:** Defensive but unused — all four inner proxies always return `PromiseInterface`.
Future inner refactor could leak through silently.
**Fix:** Replace fall-through with `throw new LogicException(...)`.

---

## Open Questions for Maintainer

1. Is `DeferredFiber.php` meant to ship? Wire it up or delete (M2).
2. Should `createCoroutine` skip the Fiber wrap for Generator-mode workflows? (M3)
3. Should `FiberHelper::await` throw when out-of-context, mirroring `\Temporal\Workflow::getCurrentContext()`? (M11)
4. Is `createTimer` (raw) intentional API asymmetry, or should it be removed? (L3)
5. Should `gather`/`runLocked` accept Generator-returning callables, or reject them? (M5, M6)
6. Is the Schedule/Fibers test duplication intentional placeholder for future client-side Fiber work? (C5)
7. Is `Logger_Test_Workflow` name collision intentional with intent to retire the non-Fiber version, or oversight? (C3)
8. Was `commit 2ac6566d` touching `WorkflowExecutionInfo.php` accidentally included on this branch? (L8)
9. Should Fiber `finally` blocks run on hard destroy? PHP semantics say no; SDK can opt in via a controlled throw. (M9)
10. Should the bridge `$fiber->getReturn()` post-throw raise the **original** user exception instead of `FiberError`? (A4 open question 4)

---

## Suggested Action Plan

Recommended ordering for `/aif-plan`:

```
Phase 1 — Embarrassments (fast, must-fix before any further demo)
- C1 (delete $a=1;)
- C5 (delete Schedule/Fibers byte-identical tests)
- C4 (delete or rewrite TaskQueue/Fibers tests)
- L2 (resolve TODO comment)

Phase 2 — Correctness (Fiber path bugs that affect real workflows)
- C6 (setFiberMode cleanup on destroy/cancel)
- C7 (force fiberMode=false in query handler)
- C8 (replace bare catch in Scope::next)
- M9 (Fiber finally on hard destroy)
- H5 (add setFiberMode regression tests — depends on C6)

Phase 3 — Test integrity (tests claiming Fiber coverage but providing none)
- C2 (remove yield from RawValueTest, ContextTest)
- C3 (rename Logger_Test_Workflow)
- H6 (rename or fix MutexYieldTest constructor)
- M12 (full-message assertions)

Phase 4 — API completeness
- H1 (awaitWithTimeout accept Mutex)
- H2 (sideEffect with options)
- H7 (gather return type / cancellability)
- H8 (runLocked auto-await)
- M7 (createExecution rename / mirror on child stub)
- M8 (signal/cancel void typing)

Phase 5 — Static-analysis & typing
- H3 (FiberProxy @mixin T)
- H4 (parallel decorator interfaces)
- L4 (template generics on facade)

Phase 6 — Unit-test gap
- M14 (unit tests for entire src/Experiments/Fibers/)
- M13 (runLocked BaseMutex branch)
- H10 (deep call-stack acceptance test)

Phase 7 — Cleanup
- M2 (delete DeferredFiber or wire it)
- M3 (skip Fiber wrap for Generator workflows)
- M4 (remove redundant setCurrentContext inside Fiber)
- L1, L3, L5, L6, L7, L8, L9, M5, M6, M10, M11, H9, H11
```

Tests-per-finding ownership lives in this document; concrete test code is **out of
scope for `/aif-explore`** and must be implemented via `/aif-plan` + `/aif-implement`
(or `/aif-fix` for individual bugs).

---

## Cross-references / file:line index (most important)

| Finding | Primary file:line |
|---|---|
| C1 | `tests/Acceptance/worker.php:128` |
| C2 | `tests/Acceptance/Extra/DataConverter/Fibers/RawValueTest.php:49` |
| C2 | `tests/Acceptance/Extra/Interceptors/Fibers/ContextTest.php:107,112` |
| C3 | `tests/Acceptance/Extra/Workflow/Fibers/LoggerTest.php:207` |
| C4 | `tests/Acceptance/Extra/TaskQueue/Fibers/{WorkflowA,WorkflowB}Test.php` |
| C5 | `tests/Acceptance/Extra/Schedule/Fibers/{ScheduleClient,ScheduleUpdate}Test.php` |
| C6 | `src/Internal/Workflow/Process/Scope.php:299-312, 207-225, 501-516` |
| C7 | `src/Internal/Workflow/Process/Process.php:54-71` |
| C8 | `src/Internal/Workflow/Process/Scope.php:417-425` |
| H1 | `src/Experiments/Fibers/Workflow.php:217` |
| H2 | `src/Experiments/Fibers/Workflow.php:231` |
| H3 | `src/Experiments/Fibers/FiberProxy.php:19` |
| H4 | `src/Experiments/Fibers/Fiber{Activity,ChildWorkflow,ExternalWorkflow}Stub.php` |
| H5 | missing — commit `5be5c6ac` |
| H6 | `tests/Acceptance/Extra/Workflow/Fibers/MutexYieldTest.php:67-69` |
| H7 | `src/Experiments/Fibers/Workflow.php:406-411` |
| H8 | `src/Experiments/Fibers/Workflow.php:377-392` |
| H9 | `src/Internal/Workflow/Process/Scope.php:611` |
| H10 | (gap — no test file owns this) |
| H11 | `tests/Acceptance/Extra/Workflow/Fibers/MutexYieldTest.php:23-35` |
| M1 | `src/Internal/Workflow/Process/Scope.php:508-510` |
| M2 | `src/Experiments/Fibers/DeferredFiber.php` |
| M3 | `src/Internal/Workflow/Process/Scope.php:470-518` |
| M4 | `src/Internal/Workflow/Process/Scope.php:484` |
| M5/M6 | `src/Experiments/Fibers/Workflow.php:188, 198, 377, 406` |
| M7 | `src/Experiments/Fibers/FiberActivityStub.php:46-48` |
| M8 | `src/Experiments/Fibers/FiberChildWorkflowStub.php:71-74` |
| M9 | `src/Internal/Workflow/Process/Scope.php` × user-side `try/finally` |
| M10 | `src/Experiments/Fibers/FiberHelper.php:28-39` |
| M11 | `src/Experiments/Fibers/FiberHelper.php:32-38` |
| M12 | `tests/Acceptance/Extra/Update/Fibers/UntypedStubTest.php:100-104`; `UpdateWithStartTest.php:61`; `tests/Acceptance/Extra/Interceptors/Fibers/ContextTest.php:55` |
| M13 | `tests/Acceptance/Extra/Workflow/Fibers/MutexRunLockedTest.php` |
| M14 | `find tests/Unit -path "*Fiber*"` (zero results) |

---

## Method-level facade parity (from A2)

All 28 base `\Temporal\Workflow` public statics are present on `Experiments\Fibers\Workflow`.
Differences worth noting:

| Base method | Fiber facade | Wrapping | Verdict |
|---|---|---|---|
| `awaitWithTimeout` | same | await; **`Mutex` missing from union** | H1 |
| `sideEffect` | same | await; **`SideEffectOptions` param dropped** | H2 |
| `runLocked` | same | helper; **return not auto-awaited** | H8 |
| `timer` | same | await | OK |
| (n/a) | `createTimer` | passthrough raw promise | L3 |
| (n/a) | `gather` | helper; **return type / cancel** | H7 |
| `async`/`asyncDetached` | same | passthrough; **Generator arm dropped from PHPDoc** | M5 |
| `executeActivity` etc. | same | await; **`mixed` widens generics** | L4 |
| `upsert{Memo,SearchAttributes,…}` | same | passthrough (sync) | OK |
| `register{Signal,Query,Update}` | same | passthrough (sync) | OK |
| `getCurrentContext`, `now`, `isReplaying`, `getInfo`, `getInput`, `getStackTrace`, `getLastCompletionResult`, `getCurrentDetails`, `setCurrentDetails`, `getLogger`, `getInstance`, `getUpdateContext`, `allHandlersFinished` | same | passthrough | OK |
| `getVersion`, `uuid`/`uuid4`/`uuid7`, `continueAsNew`, `executeChildWorkflow` | same | await | OK |
| `newActivityStub`, `newChildWorkflowStub`, `newContinueAsNewStub`, `newExternalWorkflowStub` | same | wrap in `FiberProxy` | OK with H3 |
| `newUntypedActivityStub`, `newUntypedChildWorkflowStub`, `newUntypedExternalWorkflowStub` | same | wrap in typed Fiber\*Stub | OK with H4, M7, M8 |

---

## Scenario verdicts (from A4)

| Scenario | Verdict |
|---|---|
| A: Fiber main + `async()` child | OK (separate ScopeContext) |
| B: Generator main + Fiber signal | OK (signals get fresh child scope; **prompt's worry was unfounded**) |
| C: Fiber main suspended + signal arrives | OK |
| D: Cancellation through Fiber-mode scope | OK (CanceledFailure threads through bridge) |
| E: Nested Fiber suspension | OK (no PHP-level re-entrancy) |
| F: `attach()` for Generator sub-scopes | OK; question on validating Fiber suspension type (M10) |
| G: Memory leak via Fiber holding ScopeContext | OK (refs flow correctly); see M9 for `finally` non-execution |
| H: Query handler | BUG (C7) — defensive gap |
| I: `defer()` / `nextPromise` resumption order | OK per-scope |
