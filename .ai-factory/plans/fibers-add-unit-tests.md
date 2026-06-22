# Plan: Unit-test coverage for `src/Experiments/Fibers/*` (M14 + M10)

**Branch:** `fibers` (existing — do not create a new branch)
**Date:** 2026-05-23
**Mode:** full
**Type:** test (additive, no production behavior change except a small M10 defensive assert in `FiberHelper::await`)
**Plan file:** `.ai-factory/plans/fibers-add-unit-tests.md`
**Origin:** `/aif-plan` against `.ai-factory/research/fibers-review.md` findings **M14** and **M10**.

## Settings

- Testing: **yes** — the plan IS tests; verification re-runs `composer test:unit`.
- Logging: minimal — pure test additions, no runtime logic except one defensive assert.
- Docs: **no** — internal `@experimental @internal` namespace, no public docs surface.
- Roadmap linkage: skip — no roadmap entry tracks the Fibers experiment.
- Branch creation: **skip** — the working branch is already `fibers`. The plan adds files under `tests/Unit/Experiments/Fibers/` plus one tiny edit in `src/Experiments/Fibers/FiberHelper.php`.

## Why this plan exists

`.ai-factory/research/fibers-review.md` flagged:

- **M14 (MEDIUM)** — *No unit tests at all for `src/Experiments/Fibers/*`* (8 files: `DeferredFiber`, `FiberActivityStub`, `FiberChildWorkflowStub`, `FiberExternalWorkflowStub`, `FiberHelper`, `FiberProxy`, `Mutex`, `Promise`). Acceptance tests cover E2E flows but skip every per-method branch (e.g. `Mutex::tryLock`, `Mutex::getInner`, `Promise::race/map/reduce`, decorator no-context fall-through). `find tests/Unit -path "*Fiber*"` returns zero results today.
- **M10 (MEDIUM)** — *`FiberHelper::await` doesn't validate the suspension payload type beyond the parameter type-hint.* The parameter is already `PromiseInterface`, so PHP enforces type at the API boundary, but the helper has no defensive assert that prevents a future refactor from widening the parameter. Scope-side guard (in `src/Internal/Workflow/Process/Scope.php`) is tracked separately and **out of scope** for this plan.

Goal: bring `src/Experiments/Fibers/*` to ~100% line and branch coverage via isolated unit tests, plus land one tiny `FiberHelper::await` runtime assert + the test that covers it (the M10 piece).

## Scope

### In scope

- One new test file per source file under `src/Experiments/Fibers/`.
- A minimal defensive `assert(...)` inside `FiberHelper::await` to materialise M10 inside the namespace itself, plus the corresponding unit test.
- A new test directory `tests/Unit/Experiments/Fibers/` (auto-discovered by `phpunit.xml.dist`'s `<directory suffix="TestCase.php">tests/Unit</directory>`).

### Explicitly out of scope

- Any acceptance / functional test changes.
- Any production refactor of `Scope::next` / bridge generator (M10 bridge-side guard, C6/C7/C8 fixes, M3 Generator-skip optimisation, H3/H4 typing changes, etc.).
- The `FiberProxy::__call` L9 fix (throw on non-Promise return) — see *Coordination* below for how this plan tracks that decision.
- The `DeferredFiber` keep-or-delete decision (M2 / plan P10) — see *Coordination* below.

### Parallelism statement (required wording)

> Creates new files under `tests/Unit/Experiments/Fibers/`. Safe to parallelize with ALL other Fiber-fix plans. Depends LOOSELY on plan P10 (DeferredFiber decision) — if DeferredFiber is deleted, the corresponding test file is skipped.

## Test conventions to follow (project rules)

Drawn from `CLAUDE.md`, `tests/Unit/Workflow/MutexTestCase.php`, and `tests/Unit/Schedule/ScheduleTestCase.php`:

- File suffix `*TestCase.php` (Unit suite auto-discovery key — see `phpunit.xml.dist` `<directory suffix="TestCase.php">tests/Unit</directory>`).
- `declare(strict_types=1);` at the top.
- Namespace `Temporal\Tests\Unit\Experiments\Fibers` (mirrors `tests/Unit/Experiments/Fibers/`).
- Extend `PHPUnit\Framework\TestCase` directly (matches existing style in `MutexTestCase.php`, `ScheduleTestCase.php`).
- `#[CoversClass(...)]` on every class.
- `#[UsesClass(...)]` for every external production class the test instantiates (per the Nexus-style rule in `CLAUDE.md`).
- Method naming `testFoo` — do NOT add `#[Test]` attribute (existing project style is mixed; this plan picks the bulk style from `MutexTestCase.php`).
- No comments inside the test body (per `CLAUDE.md` hard rule). Allowed PHPDoc only for type annotations the type system cannot express.
- Exception assertions: `expectExceptionMessage()` with the full string (per `CLAUDE.md`).
- **Never** use `markTestIncomplete()` / `markTestSkipped()` to bypass a failing test (per `CLAUDE.md`).
- Each test asserts a behavioural delta — no tautologies (per `CLAUDE.md` "Tests must exercise real behaviour"). Constructor smoke-tests that only read back a constructor argument are banned.
- Strong type hints; no untyped variables.

### Facade pollution mitigation — single decision

The `Facade` class is `Temporal\Internal\Support\Facade` and holds a single static slot `private static ?object $ctx`. Any test that calls `Facade::setCurrentContext($x)` MUST restore the slot to `null` in `tearDown`, otherwise the next test in the same process sees the stale context.

**Decision: do NOT introduce a shared base class.** Each affected test file declares its own `tearDown()` that calls `Facade::setCurrentContext(null)`. Three test files touch the facade: `FiberHelperTestCase`, `FiberProxyTestCase`, plus each decorator test file that includes the "in-fiber" test path (T-ActivityStub, T-ChildStub, T-ExternalStub, T-Promise, T-Mutex). The tearDown is three lines; the project's existing unit tests don't use a shared fiber-aware base, and adding one introduces a one-off helper outside the established style.

The three lines:

```php
protected function tearDown(): void
{
    Facade::setCurrentContext(null);
    parent::tearDown();
}
```

## Mocking strategy per file

The Fibers namespace is 5 decorator classes + 1 helper + 1 value-wrapper + 1 promise facade. Pick the lightest tool per file and stick with it:

| File under test                       | Inner collaborator                     | Strategy                                                                                          |
|---------------------------------------|----------------------------------------|---------------------------------------------------------------------------------------------------|
| `FiberHelper`                          | `Facade` static slot + `ScopeContext`  | `createMock(ScopeContext::class)` for the `isFiberMode()` switch; reset `Facade::setCurrentContext(null)` in `tearDown`. Real `\Fiber` for the in-fiber path. |
| `FiberProxy`                           | any `object` with `__call`             | hand-rolled top-level fake class in the same file (`final class FiberProxyInnerFake { public string $lastMethod=''; public array $lastArgs=[]; public mixed $next=null; public function __call(string $m, array $a): mixed { $this->lastMethod = $m; $this->lastArgs = $a; return $this->next; } }`), defined inside the test namespace below the test class. |
| `Mutex`                                | `Temporal\Workflow\Mutex` (real)       | use the real `BaseMutex` — it has no external dependencies (`tests/Unit/Workflow/MutexTestCase.php` already exercises it independently). |
| `Promise`                              | `\Temporal\Promise` static facade      | use real `\Temporal\Promise::resolve()` / `::reject()` (synchronous, no loop required for the resolve/reject + race/map/reduce branches under test). Avoid touching anything that requires the workflow event loop. **Plus** `Facade::setCurrentContext(null)` in `tearDown` since `Promise::all` etc. call `FiberHelper::await` which reads the facade. |
| `DeferredFiber` *(conditional)*        | real `\Fiber`                          | real `\Fiber` instances; no mocks needed.                                                         |
| `FiberActivityStub`                    | `ActivityStubInterface`                | `createMock(ActivityStubInterface::class)`.                                                       |
| `FiberChildWorkflowStub`               | `ChildWorkflowStubInterface`           | `createMock(ChildWorkflowStubInterface::class)`.                                                  |
| `FiberExternalWorkflowStub`            | `ExternalWorkflowStubInterface`        | `createMock(ExternalWorkflowStubInterface::class)`.                                               |

For decorator tests, the test must verify both that:
1. the inner method is called with the forwarded arguments (mock expectation),
2. when the inner returns a `PromiseInterface`, the result is the awaited value (out-of-fiber path: the promise itself; in-fiber path: the resolved value).

The default test mode is **out-of-fiber** (no `Facade` context set), so `FiberHelper::await` returns the promise unchanged. Each decorator gets at least one **in-fiber** test that:
- runs the assertion inside a `\Fiber` callable,
- pre-sets `Facade::setCurrentContext($mockScopeContextWithFiberMode=true)`,
- exits the fiber, and asserts the resumed value.

### In-fiber test scaffolding (canonical pattern)

`\Fiber::start()` returns the value passed to the first `\Fiber::suspend()` inside the fiber callable; subsequent `\Fiber::resume($value)` pushes `$value` back as the return value of `suspend` inside the fiber. All in-fiber tests use this loop verbatim:

```php
$context = $this->createMock(ScopeContext::class);
$context->method('isFiberMode')->willReturn(true);
Facade::setCurrentContext($context);

$promise = \Temporal\Promise::resolve('inner-result');
$captured = null;

$fiber = new \Fiber(function () use ($subject, $promise, &$captured): void {
    $captured = $subject->methodUnderTest(...);
});

$suspended = $fiber->start();
self::assertSame($promise, $suspended);

$fiber->resume('inner-result');
self::assertTrue($fiber->isTerminated());
self::assertSame('inner-result', $captured);
```

For decorator tests, `$subject->methodUnderTest(...)` returns the awaited value (via `FiberHelper::await`), so the variable captured inside the fiber is what the resume pushed back.

## Coordination with sibling Fiber plans

| Sibling plan                                                       | Coupling                                                                                                                                        | This plan's response                                                                                                                                                                                          |
|--------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| P10 *(DeferredFiber: keep-and-wire vs delete; M2)*                 | If `DeferredFiber.php` is deleted, the test file disappears. If wired up, the existing tests apply.                                              | Task **T-DF** is **conditional**: implementer first runs `test -f src/Experiments/Fibers/DeferredFiber.php` — if absent, skip T-DF entirely (no test file is created). If present, follow the test outline below. |
| L9 *(FiberProxy: throw on non-Promise inner return)*               | Currently `FiberProxy::__call` returns the inner result as-is when it isn't a `PromiseInterface`. L9 wants it to `throw new \LogicException(...)`. | This plan ships the **current** behaviour test (returns scalar through). When L9 lands, the test method `testReturnsNonPromiseAsIsCurrentBehaviour` is renamed and flipped to `expectException(\LogicException::class)`. The drift is intentional and serves as the canary signal. |
| H3 *(FiberProxy @template T / @mixin T PHPDoc)*                    | Pure PHPDoc; tests are runtime-only.                                                                                                            | No impact.                                                                                                                                                                                                    |
| H4 *(parallel decorator interfaces)*                               | Adds new interfaces; existing classes stay implementing/decorating same shapes.                                                                  | No impact — tests are written against the concrete decorator classes, not interfaces.                                                                                                                          |
| M7 *(`FiberActivityStub::createExecution` rename)*                 | Renames `createExecution` → `executeAsync`/`executeRaw`.                                                                                         | Test method names use the **current** API (`createExecution`). If the rename lands, that one test method is renamed accordingly. The implementer must NOT pre-empt the rename.                                  |
| M8 *(`signal`/`cancel` return type → `void`)*                      | Decorator methods become `: void`.                                                                                                              | Tests assert that the inner is invoked with the right args; do not assert on return value beyond `null`. Stable across the change.                                                                              |

> **Loose dependency declaration:** this plan ships **independently** of all other Fiber fixes. If sibling plans land first, individual tests will need small adjustments (covered above); T-Verify (below) re-runs `composer test:unit` and exposes any drift before the plan is marked done.

## Task list

Tasks are designed for **maximum parallelism**: T-Helper, T-Proxy, T-Mutex, T-Promise, T-ActivityStub, T-ChildStub, T-ExternalStub, T-DF are all independent files. They can be assigned to N parallel `aif-implement` workers. T-Assert (the FiberHelper assert addition for M10) must land before T-Helper's `testAssertFiresOnInvalidSuspensionPayload` test method is added but the rest of T-Helper can run in parallel with T-Assert.

Ordering only matters at the very end: T-Verify must run after all file tasks.

### T-Assert — Add defensive assert in `FiberHelper::await` (M10)

**Files touched:**
- `src/Experiments/Fibers/FiberHelper.php` (1 line edit)

**Change:** Inside the `if ($context instanceof ScopeContext && $context->isFiberMode())` branch, immediately before `\Fiber::suspend($promise)`, add:

```php
\assert(
    $promise instanceof PromiseInterface,
    'FiberHelper::await suspension payload must be a PromiseInterface',
);
```

**Rationale:** The parameter type-hint already enforces this at call time. The `\assert` adds a defense-in-depth marker so a future refactor that loosens the parameter type (e.g. `mixed $promise`) doesn't silently lose the check. `\assert` is zero-cost when `zend.assertions=-1` in production; tests run with assertions enabled (default `zend.assertions=1` in dev/test).

**Acceptance criteria:**
- The file still passes `composer cs:diff` and `composer psalm`.
- No other production change.

**Dependencies:** none.

**Out of scope:** the bridge-side type-guard (Scope::next) — that's a separate plan.

---

### T-Helper — `tests/Unit/Experiments/Fibers/FiberHelperTestCase.php`

**Subject:** `Temporal\Experiments\Fibers\FiberHelper`

**Inner collaborators mocked:** `ScopeContext` (via `createMock`); `Facade` static slot is reset in `tearDown`.

**Test methods (each is an independent behaviour):**

1. `testReturnsPromiseUnchangedWhenNoContext` — `Facade::setCurrentContext(null)` (the default in `tearDown`); pass a resolved promise; assert `FiberHelper::await($p) === $p` (the literal same instance).
2. `testReturnsPromiseUnchangedWhenContextIsNotScopeContext` — set `Facade::setCurrentContext(new \stdClass())`; assert the promise is returned as-is.
3. `testReturnsPromiseUnchangedWhenScopeContextHasFiberModeFalse` — mock `ScopeContext` with `isFiberMode()` returning `false`; assert the promise is returned as-is.
4. `testSuspendsCurrentFiberWhenContextIsFiberMode` — set `Facade::setCurrentContext($mockWithFiberModeTrue)`; run inside a `\Fiber`; assert that `\Fiber::start()` returns the original promise (proving the suspend happened with the expected payload); resume with a sentinel value; assert the fiber terminated and the sentinel was returned from `await()`.
5. `testTypeErrorOnNonPromiseArgument` (covers **M10** — user-facing contract) — call `FiberHelper::await(42)` (a literal int) from outside any fiber; PHP's parameter type-hint raises `\TypeError`. `expectException(\TypeError::class)`. **This is the only M10 test method.** Rationale: the contract is enforced at the API boundary by PHP itself; bypassing it via Reflection just to fire the new `\assert` would test PHP internals rather than the user-visible contract. The `\assert` from T-Assert remains as a defense-in-depth marker for the case where a future refactor widens the parameter; that future change would be accompanied by its own test.

**Acceptance criteria:**
- All 5 methods green.
- `Facade::getCurrentContext()` is `null` after every test (verified by `tearDown`).
- `#[CoversClass(FiberHelper::class)]` at the class level; `#[UsesClass(ScopeContext::class)]` for the mock.

**Dependencies:** **none** — test #5 tests the existing PHP-level type-hint behaviour and is independent of T-Assert (which adds the deeper `\assert` rather than a hard exception). If a future change replaces the type-hint with `mixed $promise`, T-Assert's `\assert` becomes the load-bearing guard and a new test method covers it.

---

### T-Proxy — `tests/Unit/Experiments/Fibers/FiberProxyTestCase.php`

**Subject:** `Temporal\Experiments\Fibers\FiberProxy`

**Inner collaborators mocked:** hand-rolled `final class FiberProxyInnerFake` defined in the same file (top-level, same test namespace, below the test class). Has `public string $lastMethod`, `public array $lastArgs`, `public mixed $next`, and a `__call(string $m, array $a): mixed` that records call info and returns `$this->next`.

**Test methods:**

1. `testForwardsMethodNameAndArgumentsToInner` — assert `lastMethod === 'foo'`, `lastArgs === [1, 'bar']` after a `$proxy->foo(1, 'bar')` call.
2. `testReturnsPromiseAsAwaitResultOutOfFiber` — `$fake->next = \Temporal\Promise::resolve('value');`; out-of-fiber → `FiberHelper::await` returns the promise; assert the proxy returned the promise (literal same instance).
3. `testReturnsNonPromiseAsIsCurrentBehaviour` (the L9 BEFORE state) — `$fake->next = 42;`; assert `$proxy->foo() === 42`. **This test is intentionally fragile and will need updating when L9 lands** — that is the desired signal.
4. `testSuspendsFiberWhenInnerReturnsPromiseInFiberMode` — set fiber-mode context, run inside a `\Fiber`; assert `\Fiber::start()` returns the promise; resume with `'resolved'`; assert the fiber terminated with `'resolved'`.

**Acceptance criteria:**
- All 4 methods green.
- `#[CoversClass(FiberProxy::class)]`; `#[UsesClass(FiberHelper::class)]`; `#[UsesClass(ScopeContext::class)]`.

**Dependencies:** none.

---

### T-Mutex — `tests/Unit/Experiments/Fibers/MutexTestCase.php`

**Subject:** `Temporal\Experiments\Fibers\Mutex`

**Inner collaborator:** real `Temporal\Workflow\Mutex` (no mocks — covered already by `tests/Unit/Workflow/MutexTestCase.php` and has no external deps).

**Test methods:**

1. `testGetInnerReturnsTheUnderlyingBaseMutex` — assert `$m->getInner() instanceof BaseMutex` and that consecutive calls return the same instance.
2. `testIsLockedFalseInitially` — assert `$m->isLocked() === false`.
3. `testTryLockSucceedsThenFailsThenSucceedsAfterUnlock` — `assertTrue($m->tryLock()); assertFalse($m->tryLock()); $m->unlock(); assertTrue($m->tryLock());`.
4. `testIsLockedTracksTryLockAndUnlock` — interleave `tryLock`/`unlock` and read `isLocked()` after each.
5. `testLockReturnsResolvedPromiseImmediatelyWhenUnlocked` — first `lock()` out-of-fiber returns whatever `BaseMutex::lock()` returns; since out-of-fiber `FiberHelper::await` is a passthrough, assert the return is a `PromiseInterface` and it resolves immediately (use `Promise::then()` to capture the value).
6. `testDoubleUnlockBehaviour` — call `unlock()` when not locked; assert what `BaseMutex` does. **Implementation note**: before writing the test, read `src/Workflow/Mutex.php` to discover the actual contract (does `unlock()` throw, no-op, or assert?). Pin the test to that contract. **No `markTestIncomplete` allowed** — pick whichever assertion matches reality.
7. `testLockSuspendsFiberInFiberMode` — set fiber-mode context, run `$m->lock()` inside a fiber; the inner `lock()` returns a resolved promise (since unlocked), so the suspension payload is that resolved promise. Assert `\Fiber::start()` returns the inner promise instance, then resume with a sentinel and verify the fiber terminates.

**Acceptance criteria:**
- All 7 methods green.
- `#[CoversClass(Mutex::class)]`; `#[UsesClass(BaseMutex::class)]`; `#[UsesClass(FiberHelper::class)]`.

**Dependencies:** none.

---

### T-Promise — `tests/Unit/Experiments/Fibers/PromiseTestCase.php`

**Subject:** `Temporal\Experiments\Fibers\Promise`

**Inner collaborator:** real `\Temporal\Promise` (synchronous facade — no event loop required for resolve/reject + the combinators tested in unit mode out-of-fiber).

**Pre-flight read (implementer):** open `src/Promise.php` and read `all`, `any`, `some`, `race`, `map`, `reduce`. The Fiber `Promise` facade delegates to these one-for-one, so the **observable behaviour** of the Fiber facade is exactly what the underlying React-style combinator returns. The tests below capture each value via a `then()` callback and assert the captured value. If the underlying combinator's semantics differ from the assertion below, fix the assertion to match — do NOT change the production code.

**Test methods (one per public method, plus edge cases):**

1. `testResolveReturnsPromiseInterface` — `Promise::resolve(42)` returns `PromiseInterface`; assert via `then()` callback captures `42`.
2. `testResolveWithPromiseInterfaceReturnsSamePromise` — pass an existing promise; assert it's returned as-is (delegates to `\Temporal\Promise::resolve`).
3. `testRejectReturnsRejectedPromise` — assert `Promise::reject('reason')` invokes the rejection handler with `'reason'`.
4. `testAllOutOfFiberReturnsAggregatePromise` — `Promise::all([resolve(1), resolve(2)])` out-of-fiber → returns `PromiseInterface`; via `then()` capture; assert `[1, 2]`.
5. `testAnyOutOfFiberReturnsFirstResolved` — `Promise::any([reject('a'), resolve(2), resolve(3)])` → captures `2`.
6. `testSomeOutOfFiberReturnsRequestedCount` — `Promise::some([resolve(1), resolve(2), resolve(3)], 2)` → assert the captured value is `[1, 2]` (React-promise `some(N)` returns the first N resolved values in array form, keyed by original index — adjust to actual contract after reading `\Temporal\Promise::some`).
7. `testRaceOutOfFiberPicksFirstSettlement` — `Promise::race([resolve(1), resolve(2)])` → captures `1`.
8. `testRaceMixedValuesAndPromises` (edge case — covers the `iterable<PromiseInterface<T>|T>` PHPDoc shape) — `Promise::race([42, resolve(99)])` → the bare value `42` should resolve first; assert capture is `42`.
9. `testMapAppliesCallableToResolvedValues` — `Promise::map([resolve(1), resolve(2)], fn($v) => $v * 10)` → captures `[10, 20]`.
10. `testMapWithEmptyIterable` (edge case) — `Promise::map([], fn($v) => $v)` → captures `[]`.
11. `testReduceAccumulatesAcrossPromises` — `Promise::reduce([resolve(1), resolve(2), resolve(3)], fn($acc, $v) => $acc + $v, 0)` → captures `6`.
12. `testReduceWithoutInitial` — `Promise::reduce([resolve(2), resolve(3)], fn($acc, $v) => ($acc ?? 0) + $v)` → captures `5`.
13. `testAllSuspendsFiberWhenInFiberMode` — set fiber-mode context, run inside `\Fiber`; assert `\Fiber::start()` returns the aggregate promise; resume with `[1, 2]`; assert fiber terminates with `[1, 2]`.

**Acceptance criteria:**
- All 13 methods green.
- `#[CoversClass(Promise::class)]`; `#[UsesClass(FiberHelper::class)]`; `#[UsesClass(\Temporal\Promise::class)]`.
- **No assertion of internal `\Temporal\Promise` shape** — these tests verify the Fiber facade forwards correctly, not the underlying React promise semantics.

**Dependencies:** none.

---

### T-ActivityStub — `tests/Unit/Experiments/Fibers/FiberActivityStubTestCase.php`

**Subject:** `Temporal\Experiments\Fibers\FiberActivityStub`

**Inner collaborator:** `createMock(ActivityStubInterface::class)`.

**Test methods:**

1. `testGetOptionsDelegatesToInner` — mock `inner::getOptions()` returns a fake `ActivityOptionsInterface`; assert decorator returns the same instance and inner was called once with no args.
2. `testExecuteForwardsArgumentsAndAwaitsInner` — out-of-fiber: mock `inner::execute('myAct', [1,2], $type, true)` returns `\Temporal\Promise::resolve('ok')`; assert decorator returns the resolved promise instance (literal same — passthrough out-of-fiber).
3. `testExecuteDefaultArguments` — assert default `$args=[]`, `$returnType=null`, `$isLocalActivity=false` flow through to inner correctly.
4. `testCreateExecutionForwardsToInnerExecuteRaw` (covers M7's "current behaviour" — `createExecution` aliases `execute`) — mock `inner::execute(...)` is called with the same arg shape; decorator returns the promise **without awaiting** (so the return is the raw promise, not the resolved value, regardless of fiber mode); run once out-of-fiber and once in-fiber and assert the result is the same `PromiseInterface` instance in both cases. **This test is the canary for M7** — when the rename lands, this test moves to `executeAsync` / `executeRaw`.
5. `testExecuteSuspendsFiberInFiberMode` — set fiber-mode context, run inside a fiber; assert `\Fiber::start()` returns the inner promise; resume with `'ok'`; assert fiber terminates with `'ok'`.

**Acceptance criteria:**
- All 5 methods green.
- `#[CoversClass(FiberActivityStub::class)]`; `#[UsesClass(FiberHelper::class)]`; `#[UsesClass(ActivityStubInterface::class)]`.

**Dependencies:** none.

---

### T-ChildStub — `tests/Unit/Experiments/Fibers/FiberChildWorkflowStubTestCase.php`

**Subject:** `Temporal\Experiments\Fibers\FiberChildWorkflowStub`

**Inner collaborator:** `createMock(ChildWorkflowStubInterface::class)`.

**Test methods (one per public method):**

1. `testGetExecutionDelegatesAndAwaits` — out-of-fiber: mock returns `Promise::resolve($execution)`; assert decorator returns the promise as-is.
2. `testGetChildWorkflowTypeIsSyncPassthrough` — mock returns `'MyWorkflow'`; assert decorator returns the string (no fiber/promise involvement).
3. `testGetOptionsIsSyncPassthrough` — assert the `ChildWorkflowOptions` is returned as-is.
4. `testStartForwardsArgsAndAwaits` — `$stub->start(1, 'a', new \stdClass())` — mock expects exactly those args; out-of-fiber returns the promise; in-fiber suspends and resumes.
5. `testGetResultForwardsReturnTypeAndAwaits` — pass a return type; assert mock is called with it; assert decorator awaits.
6. `testExecuteForwardsArgsArrayAndReturnType` — pass `args=[1,2]`, `returnType='string'`; assert mock called with same; assert decorator awaits.
7. `testSignalForwardsNameAndArgsAndAwaits` — `$stub->signal('go', [1,2])`; mock expects same; out-of-fiber returns promise; in-fiber suspends/resumes.
8. `testStartSuspendsFiberInFiberMode` — pick one method (start) and verify the suspend/resume loop end-to-end (the others share `FiberHelper::await` logic — testing it once per decorator is enough; **do not** duplicate the full fiber dance per method).

**Acceptance criteria:**
- All 8 methods green.
- `#[CoversClass(FiberChildWorkflowStub::class)]`; `#[UsesClass(FiberHelper::class)]`; `#[UsesClass(ChildWorkflowStubInterface::class)]`; `#[UsesClass(WorkflowExecution::class)]`.

**Dependencies:** none.

---

### T-ExternalStub — `tests/Unit/Experiments/Fibers/FiberExternalWorkflowStubTestCase.php`

**Subject:** `Temporal\Experiments\Fibers\FiberExternalWorkflowStub`

**Inner collaborator:** `createMock(ExternalWorkflowStubInterface::class)`.

**Test methods:**

1. `testGetExecutionIsSyncPassthrough` — mock returns a `WorkflowExecution`; assert decorator returns the same instance (NO `FiberHelper::await` wrapping per the current source).
2. `testSignalForwardsAndAwaits` — out-of-fiber: mock returns `Promise::resolve(null)`; assert decorator returns the promise as-is.
3. `testCancelForwardsAndAwaits` — out-of-fiber: mock returns `Promise::resolve(null)`; assert decorator returns the promise as-is.
4. `testSignalSuspendsFiberInFiberMode` — set fiber context, run inside fiber, assert the suspend/resume loop terminates correctly.

**Acceptance criteria:**
- All 4 methods green.
- `#[CoversClass(FiberExternalWorkflowStub::class)]`; `#[UsesClass(FiberHelper::class)]`; `#[UsesClass(ExternalWorkflowStubInterface::class)]`; `#[UsesClass(WorkflowExecution::class)]`.

**Dependencies:** none.

---

### T-DF *(CONDITIONAL)* — `tests/Unit/Experiments/Fibers/DeferredFiberTestCase.php`

**Pre-flight check:** the implementer runs:

```sh
test -f src/Experiments/Fibers/DeferredFiber.php && echo present || echo absent
```

If `absent` (plan P10 deleted `DeferredFiber.php`), **skip this task entirely** — no test file is created. Note this in the implementation log.

If `present`, write:

**Subject:** `Temporal\Experiments\Fibers\DeferredFiber`

**Inner collaborator:** real `\Fiber`.

**Test methods:**

1. `testConstructWithAlreadyTerminatedFiberMarksFinishedAndCapturesReturn` — build a `\Fiber` whose callable returns immediately; start it (it terminates); pass to `new DeferredFiber($fiber)`; assert `isRunning() === false`, `current() === null`, `getReturn() === <expected>`.
2. `testConstructWithSuspendedFiberStoresInitialSuspendedValue` — build a fiber that suspends; start it; pass `new DeferredFiber($fiber, $initialValue)`; assert `isRunning() === true`, `current() === $initialValue`, `getReturn()` throws `\LogicException` with the full message `'Cannot get return value of a Fiber that has not finished.'`.
3. `testSendResumesFiberAndReturnsNextSuspendedValue` — fiber that suspends twice then returns; first `send($v1)` returns the second suspended value; second `send($v2)` causes the fiber to terminate; assert `isRunning() === false`, `getReturn() === <expected>`.
4. `testSendOnFinishedFiberThrowsLogicException` — finish the fiber via `send`; call `send` again; assert `\LogicException` with the full message `'Cannot send value to a Fiber that has already finished.'`.
5. `testThrowInjectsExceptionAndAdvances` — fiber that catches an injected exception and continues; `throw(new \RuntimeException('x'))` causes the fiber to either re-suspend (assert `current()` is the next yielded value) or finish (assert state); pick the simpler shape.
6. `testThrowOnFinishedFiberThrowsLogicException` — finish first; then `throw(new \RuntimeException('x'))`; assert `\LogicException` with the full message `'Cannot throw exception into a Fiber that has already finished.'`.
7. `testCatchRegistersHandlerAndFiresOnUncaughtFiberException` — fiber that throws synchronously after first resume; register two `catch()` handlers; call `send($v)`; the fiber's uncaught exception triggers each catcher in order; the original exception bubbles out (re-thrown by `handleException`). Assert that both catchers received the same exception instance, and that the bubbled exception is `=== $original`. Use `expectException` + a side-channel list to capture catcher calls.
8. `testCatchHandlerExceptionsAreSwallowed` — install a catcher that throws; verify the original exception still bubbles out from `send`, and a second catcher (added before the throwing one or after — pick the documented order) **does** still run. The current source iterates catchers in registration order and swallows catcher exceptions; this test pins that contract.
9. `testGetReturnRequiresFinishedState` — already partially covered by #2; explicit standalone for clarity.

**Acceptance criteria:**
- All 9 methods green.
- `#[CoversClass(DeferredFiber::class)]`; `#[UsesClass(CoroutineInterface::class)]`.
- The test file uses real `\Fiber` only — no PHPUnit mocking of the fiber.

**Dependencies:** **conditional on P10**. If `DeferredFiber.php` is deleted before this task runs, drop the task and the file.

---

### T-Verify — Final verification (sequential, runs last)

1. `composer cs:diff` — passes for all new test files and the FiberHelper edit.
2. `composer psalm` — clean (no new psalm errors; new `psalm-baseline.xml` entries are **not** acceptable).
3. `composer test:unit` — all new tests green; full suite passes.
4. Coverage check (optional but recommended):
   ```sh
   XDEBUG_MODE=coverage vendor/bin/phpunit --testsuite=Unit \
     --coverage-text --filter='Experiments\\\\Fibers' \
     --coverage-filter=src/Experiments/Fibers
   ```
   Target: **≥95% line coverage** on `src/Experiments/Fibers/*` (100% is the goal; allow a 5% buffer for unreachable branches like `DeferredFiber::handleException`'s `if ($this->finished)` first-check, which is a safety net that's hard to hit in practice).
5. Confirm `Facade::getCurrentContext() === null` across the suite by grep: `grep -rn "Facade::setCurrentContext" tests/Unit/Experiments/Fibers/` — every file that calls `setCurrentContext` MUST also define a `tearDown()` that resets to `null`.

**Acceptance criteria:**
- Steps 1-3 pass.
- Step 4 (coverage) ≥95% on the namespace.
- Step 5 confirmed.

**Dependencies:** all prior tasks (T-Assert, T-Helper, T-Proxy, T-Mutex, T-Promise, T-ActivityStub, T-ChildStub, T-ExternalStub, T-DF *if applicable*).

## Verification (top-level)

```sh
composer cs:diff
composer psalm
composer test:unit
```

Plus the coverage check from T-Verify.

## Risks / open questions

1. **`\Fiber` semantics in PHPUnit** — running real fibers in the unit suite is unusual but allowed. The fibers are created and driven entirely inside the test method, so no leak across tests. If a fiber is left suspended at end of a test, PHP will warn on shutdown; tests must always either resume to termination or `unset($fiber)`.
2. **`Facade` static slot pollution** — the slot is `private static ?object $ctx`. Cross-test pollution is the single largest hazard. The "Facade pollution mitigation" section above pins the decision: **per-file `tearDown()`, no shared base class.**
3. **`DeferredFiber` keep-or-delete** — already addressed via the conditional T-DF task.
4. **`Mutex::unlock()` baseline behaviour for double-unlock** — implementer determines from `src/Workflow/Mutex.php` whether it throws, no-ops, or asserts; pin the test to whatever the production contract is **today**. Do not change production behaviour from within this plan.
5. **M10 production assert** — using `\assert(...)` (not `if (!instanceof) throw`) is deliberate. Per CLAUDE.md, `\assert` is appropriate for invariants the type system cannot express at higher levels; here it's a defensive marker. If reviewers prefer a hard `\TypeError`, the implementation switches to `throw new \TypeError(...)` and the corresponding test asserts on the TypeError shape.
6. **`Promise::some` exact return shape** — depends on `\Temporal\Promise::some`'s implementation. The pre-flight read in T-Promise pins this; the assertion adjusts to match the real contract.

## Out-of-scope reminders

- Do NOT fix the bridge-side type-guard in `Scope::next`. That's tracked separately.
- Do NOT rename `createExecution` → `executeAsync` (M7) — separate plan.
- Do NOT touch `FiberProxy::__call`'s non-Promise return path beyond writing a test that pins current behaviour (L9 lives in its own plan).
- Do NOT introduce a runtime check that throws `OutOfContextException` from `FiberHelper::await` (M11) — separate plan / separate decision.
- Do NOT add new acceptance tests. This plan is unit-only.

## Definition of done

- 7 new test files created under `tests/Unit/Experiments/Fibers/` (8 if `DeferredFiber.php` still exists).
- 1 small edit to `src/Experiments/Fibers/FiberHelper.php` (the `\assert(...)` line).
- All tests pass under `composer test:unit`.
- `composer cs:diff` and `composer psalm` clean.
- Coverage on `src/Experiments/Fibers/*` ≥95% line.
- `Facade::getCurrentContext()` is `null` after every test (no cross-test pollution).
- No `markTestIncomplete` / `markTestSkipped` anywhere in the new files.
- No comments in any new test file beyond PHPDoc type annotations the type system cannot express.

