# Plan: Fix three Mutex test findings in the Fibers acceptance suite (H6, H11, M13)

**Branch:** fibers
**Date:** 2026-05-23
**Mode:** full
**Type:** test correctness / rename / coverage gap
**Source findings:** `.ai-factory/research/fibers-review.md` — H6 (HIGH, lines 214–225), H11 (HIGH, lines 268–273), M13 (MEDIUM, lines 370–374)

## Settings

- Testing: yes — every affected test must keep passing under `composer test:accept-fast --filter=Mutex`. New coverage in M13 adds a third assertion case that must pass green.
- Docs: no — internal acceptance-test fixtures only, no public-facing API change.
- Logging: minimal — single-file edits per finding, no runtime/SDK code changed.
- Roadmap linkage: none (Fibers branch cleanup pass).

## Scope statement

This plan **touches `tests/Acceptance/Extra/Workflow/Fibers/MutexYieldTest.php` (renamed to `MutexAwaitTest.php`) and `tests/Acceptance/Extra/Workflow/Fibers/MutexRunLockedTest.php`. Safe to parallelize with other Fiber-fix plans (no overlap).** No SDK source under `src/` is modified — every finding is solvable inside the test files alone. The non-Fibers tests at `tests/Acceptance/Extra/Workflow/Mutex*.php` (if any) are not touched.

## Findings

### H6 — Constructor calls `Mutex::lock()` outside a Fiber, relying on undocumented fast-path

`tests/Acceptance/Extra/Workflow/Fibers/MutexYieldTest.php:62-69`:

```php
public function __construct()
{
    $this->mutex = new \Temporal\Experiments\Fibers\Mutex();
    $this->mutex->lock();
}
```

The constructor runs **before** `Scope::createFiberHandler` wraps the workflow method (see `src/Internal/Workflow/Process/Scope.php:479-518` — the Fiber is only constructed around `$handler($values)`, where `$handler` is the workflow method, not the constructor). Therefore inside the constructor `Facade::getCurrentContext()` either returns no `ScopeContext` or returns one with `fiberMode === false`, so `FiberHelper::await` (`src/Experiments/Fibers/FiberHelper.php:28-39`) falls through and **returns the raw `PromiseInterface` without suspending**.

This happens to work today only because the base `Mutex::lock()` (`src/Workflow/Mutex.php:39-50`) has a synchronous no-op fast path when `$this->locked === false`: it sets `$this->locked = true` and returns `Promise::resolve($this)`. The first call therefore mutates state before returning. A hypothetical second consecutive `lock()` from the constructor would land on the `$deferred = new Deferred(); $this->waiters[] = $deferred;` branch (lines 46–49), enqueue a Deferred that nothing will ever resolve, return the resulting promise to `FiberHelper::await`, which would silently discard it — leaking the Deferred and never reaching the workflow method.

Compounding the fragility: `WorkflowInit` (`src/Workflow/WorkflowInit.php`) only changes how the constructor's arguments are bound (it gets the workflow-method args), not when the constructor runs relative to the Fiber. `WorkflowInit` would not fix H6 — it still runs at instantiation time, outside the Fiber. **The only correct fix is to move `lock()` into the workflow method body**, where the Fiber wrapping is active.

The class name `MutexYieldTest` is also misleading: the file contains no `yield` and the workflow body uses the Fiber facade (`Workflow::await(...)` from `Temporal\Experiments\Fibers\Workflow`). The "Yield" suffix is a hold-over from the original Generator-mode counterpart.

### H11 — History-length polling is brittle against upstream event-count changes

`tests/Acceptance/Extra/Workflow/Fibers/MutexYieldTest.php:23-35` (inside `runWithUnblockUnblock`):

```php
$historyLength = $stub->describe()->info->historyLength;
$stub->signal('unlock');

$deadline = \microtime(true) + 5;
do {
    $description = $stub->describe();
    if (\microtime(true) > $deadline) {
        $this->fail('Signal was not processed');
    }
    // Signal + 3 Workflow Tasks
} while ($description->info->historyLength < 4 + $historyLength);
```

The `+ 4` is a magic offset over server-side event counts (SignalReceived + WorkflowTaskScheduled + WorkflowTaskStarted + WorkflowTaskCompleted under the current server build). Any upstream change — a marker emitted earlier, an extra heartbeat event, a task scheduling tweak in `tctl`/Temporal server — and this test becomes flaky for reasons unrelated to the Fiber code under test.

The fix is semantic: the workflow already mutates internal state in response to the signal (it unlocks the mutex, advances past `Workflow::await($this->mutex)`, and re-locks). Expose that state through a `#[QueryMethod]` and poll on the query. This is the same pattern used elsewhere in the Fibers acceptance suite — e.g. `tests/Acceptance/Extra/Workflow/Fibers/ChildWorkflowIdTest.php:25-31` polls `query('getChildId')` until the value is non-null.

### M13 — `runLocked`'s `BaseMutex` branch is never exercised in tests

`src/Experiments/Fibers/Workflow.php:377-392`:

```php
public static function runLocked(Mutex|BaseMutex $mutex, callable $callable): CancellationScopeInterface
{
    return self::async(static function () use ($mutex, $callable): mixed {
        if ($mutex instanceof Mutex) {
            $mutex->lock();
        } else {
            FiberHelper::await($mutex->lock());
        }

        try {
            return $callable();
        } finally {
            $mutex->unlock();
        }
    });
}
```

Every existing test in `MutexRunLockedTest.php` constructs `$this->mutex = new \Temporal\Experiments\Fibers\Mutex();` (line 64) and passes it to `Workflow::runLocked($this->mutex, ...)`. The `instanceof Mutex` branch (line 380, `Mutex` = `Temporal\Experiments\Fibers\Mutex`) is always true, leaving the `else` branch at line 382-384 — which is the one calling `FiberHelper::await($mutex->lock())` directly on a base `\Temporal\Workflow\Mutex` — completely uncovered. A regression that broke `FiberHelper::await` for non-suspending promises (e.g. M11 — silent degradation outside fiber-mode) could ship green.

The fix is to add at least one workflow + test pair that hands a `\Temporal\Workflow\Mutex` (not the Fiber wrapper) to `Workflow::runLocked` and asserts the same lifecycle invariants: the callable runs while the mutex is locked, the mutex is released after, a second `runLocked` against the same base mutex can acquire and run.

## Target end state

### File rename (H6, part 1)

`tests/Acceptance/Extra/Workflow/Fibers/MutexYieldTest.php` is renamed (via `git mv`) to `tests/Acceptance/Extra/Workflow/Fibers/MutexAwaitTest.php`. The PHP namespace inside the file changes from `Temporal\Tests\Acceptance\Extra\Workflow\Fibers\MutexYield` to `Temporal\Tests\Acceptance\Extra\Workflow\Fibers\MutexAwait`. The PHPUnit class name changes from `MutexYieldTest` to `MutexAwaitTest`. The workflow wire name in `#[WorkflowMethod(name: "Extra_Workflow_Fibers_MutexYield")]` (line 71) and the matching `#[Stub('Extra_Workflow_Fibers_MutexYield')]` attributes (lines 20, 46) change to `Extra_Workflow_Fibers_MutexAwait`.

### Constructor lock removed (H6, part 2)

The renamed file's `TestWorkflow::__construct` is reduced to just instantiating the mutex (no `lock()` call). The first `lock()` is moved to the **first line of `handle()`**, before the existing `Workflow::await($this->mutex)`. Since `handle()` runs inside the Fiber, `FiberHelper::await` correctly observes `fiberMode === true` and the suspension semantics are no longer dependent on the synchronous fast-path in base `Mutex::lock()`. The body becomes:

```php
public function __construct()
{
    $this->mutex = new \Temporal\Experiments\Fibers\Mutex();
}

#[WorkflowMethod(name: "Extra_Workflow_Fibers_MutexAwait")]
#[\Temporal\Workflow\ReturnType(Type::TYPE_ARRAY)]
public function handle(): array
{
    $this->mutex->lock();
    $this->signalProcessed = false;

    Workflow::await($this->mutex);
    $awaitLocked = $this->mutex->isLocked();

    $this->signalProcessed = true;

    $this->mutex->lock();

    Workflow::await(
        $this->mutex,
        fn() => $this->exit,
    );
    $secondLocked = $this->mutex->isLocked();

    return [$awaitLocked, $secondLocked];
}
```

The variable names `$yieldLocked` / `$awaitLocked` from the original file are renamed to `$awaitLocked` / `$secondLocked` so they describe what they actually assert (the mutex state observed after each `await`); the test assertions on `$result[0]` / `$result[1]` keep their semantics — both should still be `false` because each `await` returns only after `unlock` resolved the lock.

### Semantic polling (H11)

A new `#[\Temporal\Workflow\QueryMethod]` named `isSignalProcessed` is added to `TestWorkflow`, returning the `bool $this->signalProcessed` field set by `handle()` after the first `Workflow::await($this->mutex)` resolves. The test method `runWithUnblockUnblock` replaces the `do/while (historyLength < 4 + $historyLength)` loop with a `do/while` that polls `$stub->query('isSignalProcessed')->getValue(0) === false` with the same 5-second deadline. The `$historyLength` local and the comment `// Signal + 3 Workflow Tasks` are deleted.

A private helper on the test class, `waitForSignal(WorkflowStubInterface $stub, string $query, float $timeout = 5.0): void`, encapsulates the poll loop and is reused by both `runWithUnblockUnblock` and any future additions. It calls `$stub->query($query)->getValue(0)` in a `do/while`, fails the test on deadline, and returns once the queried flag flips to `true`.

### BaseMutex coverage (M13)

`tests/Acceptance/Extra/Workflow/Fibers/MutexRunLockedTest.php` gains:

- A new inner workflow class `TestWorkflowBaseMutex` (in the same file, same namespace) that uses `\Temporal\Workflow\Mutex` (base) instead of `\Temporal\Experiments\Fibers\Mutex` and exposes the same handle signature plus `unblock` / `exit` signals.
- The workflow registers under `#[WorkflowMethod(name: "Extra_Workflow_Fibers_MutexRunLocked_Base")]`.
- A new `#[Test]` method `runLockedWithBaseMutex` on `MutexRunLockedTest` that uses `#[Stub('Extra_Workflow_Fibers_MutexRunLocked_Base')]`, signals `unblock` then `exit`, and asserts the callable ran while the mutex was held, the mutex is released after, and the test does not throw. The assertion shape mirrors `runLockedWithGeneratorAndAwait` (lines 22–34) — same four-element result array semantics: `[unlocked-after, unblock-flag, lock-state-during, exception-class-or-null]`.

This exercises the `else` branch at `src/Experiments/Fibers/Workflow.php:382-384` — `FiberHelper::await($mutex->lock())` on a base `\Temporal\Workflow\Mutex` — for the first time. The new workflow uses only the Fiber facade (`Workflow::runLocked`, `Workflow::await`, `FiberHelper::await`) but holds a non-Fiber `Mutex`, which is the supported interop shape per the `Mutex|BaseMutex` union on `runLocked`.

### Cross-checks the final state must satisfy

- `rtk grep -rn "MutexYield" tests src testing` returns **zero** hits.
- `rtk grep -rn "MutexAwait" tests src testing` returns exactly the new file + the new wire name + the new namespace (count: 1 namespace decl + 1 class decl + 1 `WorkflowMethod` arg + 2 `Stub` args = 5 hits inside `tests/Acceptance/Extra/Workflow/Fibers/MutexAwaitTest.php`).
- `rtk grep -n "historyLength" tests/Acceptance/Extra/Workflow/Fibers/MutexAwaitTest.php` returns **zero** hits (semantic polling replaced the math).
- `rtk grep -n "Extra_Workflow_Fibers_MutexRunLocked_Base" tests/Acceptance/Extra/Workflow/Fibers/MutexRunLockedTest.php` returns exactly **two** hits (one `WorkflowMethod`, one `Stub`).
- No file outside `tests/Acceptance/Extra/Workflow/Fibers/` is modified.
- `git mv` is used for the rename so file history is preserved.

## Tasks

Tasks A1, A2, A3, B, and C are sequenced as follows: **A1 (git mv) must happen first** because A2/A3/B all edit the renamed file. C is independent of A/B and can run in parallel with any of them.

- [ ] **#A1 (H6, prep) — `git mv` the file**
  - Run `git mv tests/Acceptance/Extra/Workflow/Fibers/MutexYieldTest.php tests/Acceptance/Extra/Workflow/Fibers/MutexAwaitTest.php`.
  - Do NOT edit the file contents in this task — only the rename, so git records the move with 100% similarity.
  - Verify with `rtk git status` that the change is shown as `R  MutexYieldTest.php -> MutexAwaitTest.php` (or equivalent rename detection).

- [ ] **#A2 (H6) — rename namespace, class, and wire-name inside `MutexAwaitTest.php`**
  - In the renamed file:
    - Change the namespace declaration from `namespace Temporal\Tests\Acceptance\Extra\Workflow\Fibers\MutexYield;` to `namespace Temporal\Tests\Acceptance\Extra\Workflow\Fibers\MutexAwait;`.
    - Change the PHPUnit class name from `class MutexYieldTest extends TestCase` to `class MutexAwaitTest extends TestCase`.
    - Change `#[WorkflowMethod(name: "Extra_Workflow_Fibers_MutexYield")]` (line 71) to `#[WorkflowMethod(name: "Extra_Workflow_Fibers_MutexAwait")]`.
    - Change both `#[Stub('Extra_Workflow_Fibers_MutexYield')]` occurrences (lines 20, 46) to `#[Stub('Extra_Workflow_Fibers_MutexAwait')]`.
  - Do not touch the constructor / `handle` body in this task — that is task A3, kept separate so the rename diff is reviewable on its own.
  - Verify with `php -l tests/Acceptance/Extra/Workflow/Fibers/MutexAwaitTest.php` — must report `No syntax errors detected`.

- [ ] **#A3 (H6) — move `Mutex::lock()` from constructor into `handle()`**
  - In `tests/Acceptance/Extra/Workflow/Fibers/MutexAwaitTest.php`:
    - Delete the `$this->mutex->lock();` line from `__construct()` (was line 68 in the pre-rename file). The constructor now only instantiates the mutex.
    - Insert `$this->mutex->lock();` as the **first statement** inside `handle()`, before the existing `Workflow::await($this->mutex);`.
    - Rename the locals: `$yieldLocked` → `$awaitLocked` (state after the first `await`); `$awaitLocked` → `$secondLocked` (state after the second `await`). Update the `return` array order so the assertions in the test methods (`$result[0]`, `$result[1]`) continue to mean the same thing.
  - Do NOT add `#[WorkflowInit]` — it does not affect when the constructor runs relative to the Fiber, only how its arguments are bound. The plain `__construct() / handle()` split is the correct fix.
  - Verify: `php -l tests/Acceptance/Extra/Workflow/Fibers/MutexAwaitTest.php` — no syntax errors. The two existing tests (`runWithUnblockUnblock`, `runWithUnblockExit`) must still pass once B is also applied (B replaces the brittle wait in `runWithUnblockUnblock`).

- [ ] **#B (H11) — replace `historyLength` polling with a semantic query**
  - In `tests/Acceptance/Extra/Workflow/Fibers/MutexAwaitTest.php`:
    - Add a private boolean field `private bool $signalProcessed = false;` to `TestWorkflow`.
    - In `handle()`, set `$this->signalProcessed = true;` on the line immediately after the **first** `Workflow::await($this->mutex);` returns (i.e. after the first unlock signal has been observed). Set it back to false before the second `Workflow::await(...)` is not necessary — the test only polls between the two signals.
    - Add a query handler:
      ```php
      #[\Temporal\Workflow\QueryMethod]
      public function isSignalProcessed(): bool
      {
          return $this->signalProcessed;
      }
      ```
    - In the test class `MutexAwaitTest`, add a private helper:
      ```php
      private function waitForSignal(
          WorkflowStubInterface $stub,
          string $query,
          float $timeout = 5.0,
      ): void {
          $deadline = \microtime(true) + $timeout;
          do {
              if ($stub->query($query)->getValue(0) === true) {
                  return;
              }
              if (\microtime(true) > $deadline) {
                  $this->fail("Query '{$query}' did not return true within {$timeout}s");
              }
          } while (true);
      }
      ```
    - Replace the `historyLength` polling block in `runWithUnblockUnblock` (current lines 23–35) with:
      ```php
      $stub->signal('unlock');
      $this->waitForSignal($stub, 'isSignalProcessed');
      ```
    - Delete the obsolete `$historyLength = $stub->describe()->info->historyLength;` line and the trailing `// Signal + 3 Workflow Tasks` comment (no replacement comment — the new code is self-explanatory; per the project's no-comments rule).
  - Verify:
    - `rtk grep -n "historyLength" tests/Acceptance/Extra/Workflow/Fibers/MutexAwaitTest.php` returns no hits.
    - `rtk grep -n "describe()" tests/Acceptance/Extra/Workflow/Fibers/MutexAwaitTest.php` returns no hits (we no longer call `$stub->describe()`).
    - `php -l` on the file — no syntax errors.

- [ ] **#C (M13) — add a `BaseMutex` test for `Workflow::runLocked`**
  - In `tests/Acceptance/Extra/Workflow/Fibers/MutexRunLockedTest.php`, add a second inner workflow class **in the same file and same namespace**:
    ```php
    #[WorkflowInterface]
    class TestWorkflowBaseMutex
    {
        private \Temporal\Workflow\Mutex $mutex;
        private CancellationScopeInterface $promise;
        private bool $unblock = false;
        private bool $exit = false;
        private bool $unlocked = false;

        public function __construct()
        {
            $this->mutex = new \Temporal\Workflow\Mutex();
        }

        #[WorkflowMethod(name: "Extra_Workflow_Fibers_MutexRunLocked_Base")]
        #[\Temporal\Workflow\ReturnType(Type::TYPE_ARRAY)]
        public function handle(): array
        {
            $exception = null;
            try {
                $this->promise = Workflow::runLocked($this->mutex, $this->runLocked(...));
                $result = FiberHelper::await($this->promise);
            } catch (\Throwable $e) {
                $exception = $e::class;
            }

            $trailed = false;
            Workflow::await(
                fn() => $this->exit,
                Workflow::runLocked($this->mutex, static function () use (&$trailed): void {
                    $trailed = true;
                }),
            );

            if ($trailed) {
                throw new \Exception('The trailed runLocked must not be executed.');
            }

            return [$this->unlocked, $this->unblock, $result, $exception];
        }

        #[\Temporal\Workflow\SignalMethod]
        public function unblock(): void
        {
            $this->unblock = true;
        }

        #[\Temporal\Workflow\SignalMethod]
        public function exit(): void
        {
            $this->exit = true;
        }

        private function runLocked(): bool
        {
            Workflow::runLocked($this->mutex, function (): void {
                $this->unlocked = true;
                Workflow::await(static fn() => false);
            });

            Workflow::await(fn() => $this->unblock);
            return $this->mutex->isLocked();
        }
    }
    ```
    The shape mirrors the existing `TestWorkflow` (lines 51–125) one-for-one **except** for the mutex type and the workflow wire name. This guarantees the new test exercises the same lifecycle invariants — locked-during, unlocked-after, trailing `runLocked` correctly waits on the permanent inner lock — but goes through the `FiberHelper::await($mutex->lock())` branch at `src/Experiments/Fibers/Workflow.php:382-384`.

  - Add a new test method on `MutexRunLockedTest`:
    ```php
    #[Test]
    public function runLockedWithBaseMutex(
        #[Stub('Extra_Workflow_Fibers_MutexRunLocked_Base')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->signal('unblock');
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertTrue($result[0], 'Mutex must be unlocked after runLocked is finished');
        $this->assertTrue($result[1], 'The function inside runLocked must wait for signal');
        $this->assertTrue($result[2], 'Mutex must be locked during runLocked');
        $this->assertNull($result[3], 'No exception must be thrown');
    }
    ```
    (Note the fixed typo in the second message vs. the existing `runLockedWithGeneratorAndAwait` at line 31: `"mist wait"` → `"must wait"`. The existing test stays as-is; only the new one uses the corrected message.)

  - Verify:
    - `rtk grep -n "Extra_Workflow_Fibers_MutexRunLocked_Base" tests/Acceptance/Extra/Workflow/Fibers/MutexRunLockedTest.php` returns exactly two hits.
    - `rtk grep -n "TestWorkflowBaseMutex" tests/Acceptance/Extra/Workflow/Fibers/MutexRunLockedTest.php` returns at least one class-decl hit.
    - `php -l tests/Acceptance/Extra/Workflow/Fibers/MutexRunLockedTest.php` — no syntax errors.

- [ ] **#V (verification) — run the Mutex tests**
  - `composer test:accept-fast --filter=Mutex` — must run **four** test cases (two from `MutexAwaitTest`, two from existing `MutexRunLockedTest`) **plus** the new `runLockedWithBaseMutex` case = five total, all green.
  - If the local environment lacks a Temporal server, run:
    - `php -l tests/Acceptance/Extra/Workflow/Fibers/MutexAwaitTest.php`
    - `php -l tests/Acceptance/Extra/Workflow/Fibers/MutexRunLockedTest.php`
    - `composer cs:diff -- tests/Acceptance/Extra/Workflow/Fibers/MutexAwaitTest.php tests/Acceptance/Extra/Workflow/Fibers/MutexRunLockedTest.php`
    - `composer psalm` (no new errors against the modified files)
  - Cross-check the rename was recorded properly: `rtk git diff --stat master..HEAD -- tests/Acceptance/Extra/Workflow/Fibers/` should show `MutexYieldTest.php` deleted and `MutexAwaitTest.php` added (or as a rename, depending on git's similarity detection).

## Why this ordering

A1 (git mv) must precede A2/A3/B because all three edit the renamed file — interleaving the rename with content edits muddles `git mv`'s similarity detection and risks git seeing two unrelated changes (a delete + a new file) instead of a rename. A2 (namespace/class/wire-name) is split from A3 (the actual lock-move) so the diff is reviewable in two clean halves: a pure rename touching only identifiers, and a semantic change touching only constructor/handle. B (semantic polling) layers on top of A3 because it adds a new field and query handler to the same `TestWorkflow` class A3 edits. C (BaseMutex test) is independent — it modifies a different file (`MutexRunLockedTest.php`) and does not depend on anything from A/B.

## Out of scope (explicitly)

- Every other Fiber-review finding (C1–C8, H1–H5, H7–H10, M1–M12, M14, all L items). Each gets its own plan.
- Adding `WorkflowInit` to the renamed workflow — analyzed and rejected above; the attribute only affects argument binding, not the constructor-runs-outside-Fiber issue.
- Unit tests for `src/Experiments/Fibers/Mutex.php` — covered by M14 (separate plan for `tests/Unit/Experiments/Fibers/`).
- Fixing the misuse documented as M11 (`FiberHelper::await` silent degradation outside fiber-mode contexts) — the constructor-lock case is one of M11's two reachable triggers, but the SDK-side fix belongs in an M11-specific plan.
- Renaming or modifying `MutexRunLockedTest.php`'s existing fixture (`TestWorkflow`) — task C only **adds** a sibling workflow class; the original keeps its name and wire identifier so the existing two test methods continue to pass unchanged.

## Parallelization

Inside this plan, A1→A2→A3→B is a strict chain; C runs in parallel with any of them.

Against other Fiber-fix plans, this plan is independent because:
- It modifies exactly two files, both under `tests/Acceptance/Extra/Workflow/Fibers/`, neither of which is touched by any other published Fiber-fix plan (verified against `.ai-factory/plans/fibers-test-*.md`).
- It does not modify any `src/` file — no risk of conflicting edits to `Scope.php`, `Workflow.php`, `Mutex.php`, or `FiberHelper.php`.
- It introduces one new workflow wire name (`Extra_Workflow_Fibers_MutexAwait`), one renamed-away wire name (`Extra_Workflow_Fibers_MutexYield` → deleted from the registry once the file is renamed), and one new wire name (`Extra_Workflow_Fibers_MutexRunLocked_Base`). None of these collide with names registered elsewhere (`rtk grep -rn "Extra_Workflow_Fibers_MutexAwait\|Extra_Workflow_Fibers_MutexRunLocked_Base" tests src testing` should return zero hits before the plan is applied).

Safe to dispatch alongside the C1 (worker.php hygiene), C3 (Logger rename), C2 (RawValueTest/ContextTest yield removal), and every other H/M/L plan.
