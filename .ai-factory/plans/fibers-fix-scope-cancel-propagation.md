# Plan: Fix scope-cancel propagation in Fibers mode (CancelAbandon/childWorkflowInClosingInnerScope)

**Branch:** fibers
**Date:** 2026-05-24
**Mode:** full
**Type:** runtime-fix / fibers-experimental
**Source finding:** `.ai-factory/research/RESEARCH.md` — Subagent 1 trace of cancel-propagation timing-layer mismatch; `.ai-factory/evolutions/fibers-test-replication/artifact.md` — `Harness/ChildWorkflow/Fibers/CancelAbandon/childWorkflowInClosingInnerScope` remaining failure after Cat A/B/C/Workflow\X/Promise::race fixes.

## Settings

- Testing: yes — fix is validated only when `composer test:accept-slow` runs `Harness/ChildWorkflow/Fibers/CancelAbandon/childWorkflowInClosingInnerScope` green AND the other two `CancelAbandon` sub-tests (`childWorkflowInMainScope`, `childWorkflowInInnerScopeCancel`) stay green. Generator-mode sibling (`Harness/ChildWorkflow/CancelAbandon`) must also stay green — regression-budget zero.
- Docs: no — `Experiments\Fibers\*` is `@experimental @internal`.
- Logging: minimal — one `\Temporal\Internal\Workflow\Process\Scope::defer` may receive a fiber-aware variant; if so, no new log lines.
- Roadmap linkage: closes the last `Harness/ChildWorkflow/Fibers/*` failure.

## Failing scenario (reference)

```php
class InnerScopeCancelWorkflow {
    private CancellationScopeInterface $scope;   // a FiberScope in Fibers mode

    #[WorkflowMethod('Harness_ChildWorkflow_Fibers_CancelAbandon_InnerScopeCancel')]
    public function run(string $input) {
        $this->scope = Workflow::async(static function () use ($input) {
            $stub = Workflow::newUntypedChildWorkflowStub(...);
            $stub->start($input);
            return $stub->getResult('string');
        });
        try {
            FiberHelper::await(Promise::race([Workflow::timerPromise(5), $this->scope]));
            return 'timer';
        } catch (CanceledFailure) {
            return 'cancelled';
        }
    }

    #[\Temporal\Workflow\SignalMethod('close')]
    public function close(): void {
        $this->scope->cancel();
    }
}
```

Test: send `close` signal, then assert parent returns `'cancelled'` within 5 s. Currently times out at 60 s with `WorkflowFailedException retryState=3`.

## Root-cause hypothesis (from Subagent 1 trace)

Signal handler runs in `ON_SIGNAL` loop layer. `$this->scope->cancel()` synchronously:
1. Marks `inner.cancelled = true`
2. Iterates `inner.onCancel` handlers → rejects every in-flight deferred in inner scope (including the inner fiber's suspended `$stub->getResult('string')` promise)
3. Inner fiber's bridge generator catches the rejection, rethrows into the inner fiber → fiber unwinds → `Scope::onException(CanceledFailure)` rejects `inner.deferred`
4. `inner.deferred.promise()` is what `FiberScope::then(...)` delegates to → race promise sees rejection on one of its participants → race-promise rejects
5. Main scope (which yielded the race) gets `onRejected` callback
6. `onRejected` queues a `defer($handleError)` task on the MAIN scope's layer (`Scope::defer` at `src/Internal/Workflow/Process/Scope.php:612` — `$this->services->loop->once($this->layer, $tick)`)
7. When main layer fires, `$coroutine->throw(CanceledFailure)` reaches the main fiber's bridge → bridge calls `$fiber->throw($e)` → main fiber wakes inside `FiberHelper::await` → `try/catch (CanceledFailure)` returns `'cancelled'`

The breakage candidates (rank-ordered, validate during Task 1):

- **C1 — Layer ordering**: in Generator mode the same flow works because the main scope's coroutine yields one promise at a time and the loop drives both `ON_SIGNAL` and `ON_TICK` layers in expected order. In Fibers, the main fiber is held suspended through the bridge generator's `yield $value` — the bridge does NOT re-yield until `$fiber->resume(...)` is called. If the layer driving the defer'd throw never fires because `inner.cancel()` propagated through child-scope layers but the deferred handler ended up on a layer that the loop does not pump after `ON_SIGNAL` exits, the main fiber stays suspended.
- **C2 — `FiberScope::then` delegation gap**: `FiberScope::then(?onFulfilled, ?onRejected)` returns `$this->inner->then(...)`. The returned promise's chain is registered against `inner.deferred`. When Promise::race subscribes through `FiberScope`, it observes inner.deferred. If inner.deferred resolves/rejects AFTER `inner.cancel()` finishes (i.e., asynchronously through the bridge's unwind), race sees it correctly. But if the inner fiber's bridge generator unwind is not driven by the loop after cancel (e.g., the bridge's generator is no longer being pumped because the inner scope's coroutine was already removed from the queue), the inner.deferred is never rejected. Race never settles, main fiber never wakes.
- **C3 — Cancel reset corruption (already prevented)**: `Scope::cancel()` was previously calling `setFiberMode(false)` corrupting state — fixed in earlier session. Verify it has not re-appeared.
- **C4 — `Workflow::asyncDetached(...)` in `finally`**: the original Generator yields on the detached scope (`yield Workflow::asyncDetached(fn() => yield Workflow::timer(1))`). The Fibers mechanical conversion stripped both yields; the detached scope's lambda blocks the inner detached fiber for 1 s, but the outer `run()` never waits — finally completes immediately. If the workflow returns before the cancel ack reaches the server, the workflow finishes with the wrong status. Note: this is a DIFFERENT failure mode than C1/C2 — it would cause 'timer' or empty return, not a 60s timeout. Validate this is not in play.

## Tasks

### Task A — Reproduce + instrument
**Goal:** confirm which candidate (C1 / C2 / C3 / C4) is in play.

1. Add a temporary log line in `src/Internal/Workflow/Process/Scope.php::defer()` capturing `$this->layer`, `$tick` callable spl_object_id, `microtime`. Wrap the file's existing `\Temporal\Internal\Support\Facade::getCurrentContext()` lookup so the log only fires when `isFiberMode()` is true. (Remove this instrumentation in Task E.)
2. Add a temporary log in `src/Experiments/Fibers/FiberScope::then()` and `FiberScope::cancel()` capturing the same.
3. Add a temporary log inside the inner fiber bridge's `try/catch` in `Scope::createFiberHandler` capturing exception class + spl_object_id.
4. Run only the failing test:
   ```bash
   tests/runner.php vendor/bin/phpunit --testsuite=Acceptance --filter='Harness.ChildWorkflow.Fibers.CancelAbandon.childWorkflowInClosingInnerScope'
   ```
5. Capture the log sequence between the test's `$stub->signal('close')` call and the eventual 60 s timeout. Verify which of:
   - `FiberScope::cancel` → log: should fire on `ON_SIGNAL` layer
   - inner bridge `catch (CanceledFailure)` → log: should fire next
   - inner Scope `onException` → log: should fire next
   - `FiberScope::then` callback invocation → should reach the race-promise on-rejected
   - main Scope `defer($handleError)` → should queue on main layer
   - main bridge `$fiber->throw` → should fire when main layer pumps

The first MISSING step in this chain identifies the candidate.

**Validation:** the log file pinpoints the gap. Expected: gap is at step 4 or 6 (C1 or C2).
**Out:** investigation note appended to `.ai-factory/research/RESEARCH.md` "Active Summary" section with the identified candidate.

### Task B — Fix implementation (branches by Task A's verdict)

**Gate before implementation:** Task A produces a written log-trace. If the candidate is C2 (FiberScope::then delegation gap), Task B.2 implementation MUST include explicit pseudocode AND a passing failing test on `tests/Unit/Experiments/Fibers/FiberScopeCancelTestCase.php` BEFORE the runtime change is committed. Investigation findings without code-level spec are insufficient — review them on a discussion thread before proceeding.

#### B.1 — If C1 (layer ordering): drive main layer after `ON_SIGNAL` completes

Modify `src/Internal/Workflow/Process/Process.php` signal-handler completion path (search for `handleSignal` / `ON_SIGNAL`-driving code, currently around `Process::handleSignal()`). After the signal scope finishes, explicitly trigger one more `loop->tick()` on the main workflow scope's layer to drain any defers queued during the signal's synchronous cancel.

Sketch:
```php
private function dispatchSignal(...): void {
    // ... existing signal dispatch ...
    if ($this->scopeContext->isFiberMode()) {
        $this->services->loop->tick();
    }
}
```

**Constraint:** must not affect Generator mode — gate with `isFiberMode()`. Generator-mode signal dispatch works today; do not regress it.

#### B.2 — If C2 (FiberScope.then delegation gap): make FiberScope reactively forward rejections

Change `FiberScope::then` to return a new Promise that closes over the inner's reject path and routes through `FiberHelper`-aware resume:

Sketch (no comments, per project rule):
```php
public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface {
    return $this->inner->then($onFulfilled, $onRejected);
}
```
might need to be replaced with an explicit `Deferred` mirror so that the FiberScope's promise rejects in the SAME layer where the cancel happened, not deferred to the inner's. Validate by reproducing.

#### B.3 — If C3 (cancel reset): re-apply the `aif-implement/SKILL.md` rule "Never reset fiberMode mid-flight in `Scope::cancel`"

Verify `Scope::cancel()`, `Scope::next()`, `nextPromise`, `handleError` contain NO `setFiberMode(false)` calls. If any were added, remove them.

#### B.4 — If C4 (asyncDetached in finally): restore await semantics

Change `tests/Acceptance/Harness/ChildWorkflow/Fibers/CancelAbandonTest.php` finally block from:
```php
Workflow::asyncDetached(function () {
    Workflow::timer(1);
});
```
to:
```php
Workflow::asyncDetached(function () {
    Workflow::timer(1);
})->join();
```
(Same pattern already used in `CancelTryCancelTest`.) This is a TEST change, not a runtime change — only apply if Task A points to C4.

**Validation:** the failing test runs green; all 3 `CancelAbandon` sub-tests pass; Generator-mode sibling untouched.

### Task C — Regression sweep

1. `composer psalm` — must stay green at strict level 2.
2. `composer cs:diff` — must report zero style violations.
3. `composer test:unit` — must stay green.
4. `composer test:arch` — must stay green.
5. `composer test:func` — must stay green.
6. `composer test:accept-fast` — must stay green; especially watch `Extra/Activity/Fibers/CancelTryCancelTest` (uses similar `Workflow::async + cancel + join` pattern) and `Harness/Activity/Fibers/CancelTryCancelTest` (Activity scope cancel).
7. `composer test:accept-slow` — must stay green; especially watch `Harness/ChildWorkflow/CancelAbandon` (Generator sibling, full 3 sub-tests).

### Task D — Add regression tests

Add unit-level tests under `tests/Unit/Experiments/Fibers/FiberScopeCancelTestCase.php` (suffix `TestCase.php` per project naming rule). Cover:

1. **External cancel resumes suspended fiber**: a `FiberScope` cancelled from outside the suspended fiber, asserting `FiberHelper::await(Promise::race([…, $fiberScope]))` resumes with `CanceledFailure` within a single loop tick.
2. **`join()` on cancelled scope**: `$scope->cancel(); $scope->join();` must throw `CanceledFailure` synchronously after the cancel propagates.
3. **`join()` on settled scope**: `$scope` that resolves normally — `$scope->join()` returns the resolved value (no exception).
4. **Cancel before suspend**: `$scope->cancel()` called BEFORE any fiber suspends on it — subsequent `join()` immediately throws `CanceledFailure`.

Required per skill-context aif-implement rule "Fiber unit tests must reset Facade context in tearDown":
```php
protected function tearDown(): void {
    Facade::setCurrentContext(null);
}
```

### Task E — Clean up instrumentation

Remove the temporary log lines from Task A. Validation grep before completing:
```bash
grep -rn 'file_put_contents\|trap(\|var_dump\|print_r(' src/Experiments/Fibers/ src/Internal/Workflow/ | grep -v '\.review\.md'
```
Must return zero hits.

## Out of scope

- `ChildWorkflow/Signal` and `Signal/ChildWorkflow` typed-proxy auto-await deadlock — separate plan `.ai-factory/plans/fibers-typed-proxy-async-variant.md`.
- Workflow-runtime-wide rework — this plan is surgical, targets one symptom class.
- `Workflow::asyncDetached` refinement — only touched if C4 is the candidate.

## Risks

- **R1 — Generator-mode regression**: any change in `Process::handleSignal` or `Scope::defer` must not affect Generator workflows. Mitigation: `isFiberMode()` gate on every new behavior; Generator sibling tests in the regression sweep.
- **R2 — Time-budget**: investigation may reveal a deeper bug (e.g., loop scheduler invariant violated in Fibers mode) requiring `Process.php` rework. If Task A takes >2 h, stop and surface as a blocker — do NOT speculatively rewrite the loop driver.
- **R3 — `markTestSkipped` temptation**: hard rule (project skill-context) — if root cause can't be fixed in scope, raise as blocker; do NOT annotate the test.

## Acceptance criteria

- [ ] Task A produces a written log-trace identifying the breakage candidate.
- [ ] Task B implements the corresponding fix (B.1 / B.2 / B.3 / B.4 — one of these, not multiple).
- [ ] All 3 `Harness/ChildWorkflow/Fibers/CancelAbandon` sub-tests pass.
- [ ] Generator-mode `Harness/ChildWorkflow/CancelAbandon` untouched, still green.
- [ ] Full test pyramid green (`unit && arch && func && accept-fast && accept-slow`).
- [ ] No `file_put_contents` / `trap()` / `var_dump` / `print_r` debug calls remain.
- [ ] Regression test added under `tests/Unit/Experiments/Fibers/`.
- [ ] No `markTestSkipped`/`markTestIncomplete` added anywhere.
