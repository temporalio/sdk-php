# Plan: Fix Scope.php runtime correctness, finally semantics, perf, and style (Fibers cluster)

**Branch:** fibers
**Date:** 2026-05-23
**Mode:** full
**Type:** bug-fix + design + cleanup (single-file cluster)
**Source findings:** `.ai-factory/research/fibers-review.md` — C6, C8, M1, M3, M4, M9, H9, L1, L2 (all touching `src/Internal/Workflow/Process/Scope.php`)

## Settings

- Testing: yes — but only as **dry-run smoke checks** in this plan. Regression tests for each correctness fix are owned by plan **P12 (`fibers-add-regression-tests.md`)**, which MUST land after this plan.
- Docs: yes — class-level docblock on `Scope` gets a short note about detached-scope semantics (L1) and Fiber `finally` behaviour (M9). No `docs/` markdown changes.
- Logging: verbose — Fiber-mode lifecycle is subtle; the spike tasks below explicitly produce written notes captured in the plan's implementation log.
- Roadmap linkage: none.

## Concurrency / locking warning (READ FIRST)

**This plan LOCKS `src/Internal/Workflow/Process/Scope.php` end-to-end.** It cannot be parallelized with any other plan that touches `Scope.php` — currently none in the Fiber-fix set, but flag for awareness if a new plan is added that overlaps. **All tasks within this plan must be sequenced in a single worktree** to avoid merge conflicts within the same file. Do not split this plan across parallel implementation workers.

The only other Scope-adjacent plan is **P12 (`fibers-add-regression-tests.md`)**, which adds tests under `tests/Unit/Internal/Workflow/Process/` and `tests/Acceptance/...`; P12 does not modify `Scope.php` and is safe to draft in parallel, but it must merge **after** this plan because its assertions target the fixed behaviour.

## Scope statement

Production fixes only, single file: `src/Internal/Workflow/Process/Scope.php`.

In scope:
- Correctness bug fixes that affect real workflows (C6, C8, M1).
- Two design-level cleanups that need a spike before code change (M3, M9).
- One dead-code-or-paranoia removal (M4) that needs a behaviour-trace before deletion.
- Three pure cleanups: style violation (H9), TODO comment resolution (L2), docblock note (L1).

Out of scope (each gets its own plan or already has one):
- C1 (`worker.php` debug line) — separate plan `fibers-test-hygiene-worker.md`.
- C7 (force `fiberMode=false` in `Process.php` query handler) — separate plan; touches `Process.php`, not `Scope.php`.
- All `src/Experiments/Fibers/*` findings (H1–H8, M2, M5–M8, M10–M14, L3–L9).
- Regression tests — owned by P12 (`fibers-add-regression-tests.md`).
- Any change to `tests/`. This plan writes zero test code.

## Context recap (from the source files)

Read in full before implementation:
- `src/Internal/Workflow/Process/Scope.php` (613 lines) — the target file.
- `src/Internal/Workflow/ScopeContext.php` — owns `fiberMode` flag (`setFiberMode`, `isFiberMode`); also has a `destroy()` that unsets `$scope`, `$parent`, `$onRequest`.
- `src/Internal/Workflow/Process/DeferredGenerator.php` — wraps Fiber→Generator bridge; `start()` happens lazily on first `current()`/`send()`/`valid()`. `handleException` is `never`-returning.
- `src/Internal/Workflow/Process/CoroutineInterface.php` — common contract; `isRunning`, `current`, `send`, `throw`, `getReturn`, `catch`.

Key invariants:
1. `Scope::start()` calls `createCoroutine()`, which wraps the user handler in a Fiber (line 470–518). On first drive (`next()`), the `DeferredGenerator` lazy-starts: it invokes the closure that creates `\Fiber`, calls `$fiber->start()`, and either returns the raw result (if Fiber terminated synchronously) or returns the bridge `\Generator`.
2. The bridge `\Generator` runs `try { ... } finally { $scopeContext->setFiberMode(false); }`. This `finally` fires when the bridge generator is iterated to completion **or** when PHP GCs it (PHP runs generator `finally` on GC).
3. **However**, if the Fiber is suspended and the bridge generator is GC'd, the bridge `finally` runs — but the suspended **Fiber object itself** is destructed by PHP without its in-flight `finally` running. That is the M9 hole.
4. `Scope::destroy()` (line 299–312) unsets `$this->coroutine` along with `$context`, `$scopeContext`, etc. The order is `context->destroy()` → `scopeContext->destroy()` → `unset(...)`. After `scopeContext->destroy()` (which `unset`s `$scope`, `$parent`, `$onRequest`), the bridge `finally` calling `$scopeContext->setFiberMode(false)` will still find a live `ScopeContext` instance (the `fiberMode` property survives — `destroy()` doesn't unset it), but the wider context state is gone.
5. `Scope::cancel()` (line 207–225) early-returns for detached scopes unless `$reason instanceof DestructMemorizedInstanceException`. This is documented in the review as L1.

## Ordering within this plan (CRITICAL — do not reorder)

Three groups, sequenced. Group N+1 may **only** start after Group N's tasks are all merged in the worktree.

### Group 1 — Correctness (must land first; these are real bugs)

These are bug fixes with low design risk. Do them first because Groups 2–3 build on the resulting code shape.

- **C8** — `Scope::next:417-425` bare catch. One-line behavioural fix, no design question.
- **C6** — `setFiberMode(false)` reset in `destroy()` and `cancel()`. Mechanical defensive fix.
- **M1** — bridge `\Generator` post-throw stale-yield. Add `if ($fiber->isTerminated()) break;` after the inner `throw`.

### Group 2 — Design decisions (need spikes first)

These change semantics or perf characteristics and need a written behaviour trace before code change.

- **SPIKE-M9** → then **M9** — Fiber `finally` non-execution on hard `destroy()`.
- **SPIKE-M3** → then **M3** — skip Fiber wrap for Generator-returning handlers (perf).
- **M4** — remove redundant `\Temporal\Workflow::setCurrentContext($scopeContext)` from inside the Fiber body. Depends on M3 outcome (if M3 changes the wrap site, the `setCurrentContext` line may move or vanish).

### Group 3 — Style / docs (pure cleanup, no behavioural effect)

- **H9** — convert short-circuit-side-effect at line 611 to `if`.
- **L2** — resolve and delete `// todo ->context or ->scopeContext?` at line 443.
- **L1** — add docblock note about detached-scope semantics (early-return in `cancel`).

## Target end state

Concrete invariants the file must satisfy after this plan:

1. **C6 (a):** `Scope::destroy()` calls `$this->scopeContext?->setFiberMode(false)` **before** the `$this->scopeContext?->destroy()` call (i.e. before the context's internal references are torn down). This is defensive in case the Fiber bridge `finally` did not run (Fiber GC'd without bridge entry, or any future code path that bypasses the bridge).
2. **C6 (b):** `Scope::cancel()` calls `$this->scopeContext?->setFiberMode(false)` on the early-return path for detached scopes (line 209–212) **and** on the cancelled-already path (line 214–216), and once before the `foreach ($this->onCancel ...)` block on the normal path. (Three reset sites, each adjacent to where references would otherwise leak.)
3. **C8:** `Scope::next` lines 417–425 read:
   ```php
   try {
       if (!$this->coroutine->isRunning()) {
           $this->onResult($this->coroutine->getReturn());
           return;
       }
   } catch (\Throwable $e) {
       $this->onException($e);
       return;
   }
   ```
   No bare `catch`. Workflow exceptions surface via `onException`, not silent `onResult(null)`.
4. **M1:** Bridge `\Generator` body becomes:
   ```php
   while (!$fiber->isTerminated()) {
       try {
           $sent = yield $value;
           $value = $fiber->resume($sent);
       } catch (\Throwable $e) {
           $value = $fiber->throw($e);
           if ($fiber->isTerminated()) {
               break;
           }
       }
   }
   ```
   The `break` guards against the documented undefined behaviour of re-awaiting an already-settled promise after a caught injection. A one-line docblock above the closure states: *"Re-awaiting an already-settled promise after a caught injection is undefined; the bridge breaks on Fiber termination to avoid yielding stale values."*
5. **M9:** On `Scope::destroy()`, before unsetting `$this->coroutine`, the bridge generator is given a chance to propagate `DestructMemorizedInstanceException` into the suspended Fiber so the user's `try/finally` blocks run. Exact mechanism is the SPIKE-M9 deliverable; the default-recommended shape is calling `$this->coroutine->throw(new DestructMemorizedInstanceException())` inside a `try`/`catch (\Throwable) { /* swallow */ }` wrapper. **If the spike reveals a re-entrant destroy hazard (see SPIKE-M9 below), the fix is downgraded to a documentation-only note and a `TODO(plan-or-issue-ref)` pointer.**
6. **M3:** `createCoroutine()` detects Generator-returning handlers and routes them to the pre-Fiber path (i.e. `DeferredGenerator::fromHandler($handler, $values)` directly, no Fiber wrap). Detection mechanism is the SPIKE-M3 deliverable; the candidate is reflection on the handler (`\Closure`, `MethodHandler`, or an arbitrary `callable`) checking whether the underlying method/function has `ReflectionFunction::isGenerator() === true`. **If the spike reveals handler shapes the reflection cannot classify reliably, the M3 fix is downgraded to "Fiber wrap remains universal; perf finding deferred" and a `TODO(plan-or-issue-ref)` pointer.**
7. **M4:** Line 484 `\Temporal\Workflow::setCurrentContext($scopeContext)` is **either** removed (if the behaviour-trace task shows it is redundant under all spawn paths) **or** kept with a one-line PHPDoc-style annotation explaining the surviving case. No bare unjustified call.
8. **H9:** Line 611 short-circuit converted to `if`:
   ```php
   if ($this->services->queue->count() === 0) {
       $this->services->loop->tick();
   }
   ```
9. **L2:** TODO at line 443 (`// todo ->context or ->scopeContext?`) is resolved and deleted. The current code uses `$this->scopeContext` for the request path (`$this->context->getClient()->request($current, $this->scopeContext)`). Resolution: `$this->scopeContext` is correct — the per-scope context is what the request needs to be cancelled against. No code change beyond deleting the comment.
10. **L1:** `Scope` class-level docblock (lines 34–40) gains one line: *"Detached scopes early-return from `cancel()` unless the reason is a `DestructMemorizedInstanceException`; their cleanup is driven by the memory-flush path (see `Process::destroyScopes`)."* No prose essay — one sentence.

## Tasks

> **Sequencing:** complete every task in Group 1 before starting Group 2; complete every task in Group 2 before starting Group 3. Do not reorder.

### Group 1 — Correctness

- [ ] **#1 (C8) — Replace bare catch in `Scope::next`.**
  - In `src/Internal/Workflow/Process/Scope.php` lines 417–425, replace `} catch (\Throwable) { $this->onResult(null); return; }` with `} catch (\Throwable $e) { $this->onException($e); return; }`.
  - Rationale: the existing code silently resolves the workflow to `null` when `$this->coroutine->getReturn()` or `$this->coroutine->isRunning()` throws. With the Fiber bridge, this can happen when `$fiber->getReturn()` raises `FiberError` after an uncaught Fiber-side exception — the workflow then reports success-with-null-result instead of failing.
  - Verify with `composer psalm` and `composer cs:diff` (style only — no test added here; P12 owns the regression test).

- [ ] **#2 (C6) — Reset `fiberMode` defensively in `Scope::destroy()` and `Scope::cancel()`.**
  - In `Scope::destroy()` (line 299), add `$this->scopeContext?->setFiberMode(false);` as the first statement of the method body, **before** `$this->context?->destroy();`. Rationale: the bridge `finally` may not have run (Fiber GC'd without entering the bridge — e.g. a Fiber that throws synchronously inside `$fiber->start()` is partially handled at line 491, but a future code path that bypasses that hook would leak); the reset is now defence-in-depth.
  - In `Scope::cancel()` (line 207), add `$this->scopeContext?->setFiberMode(false);` in three places:
    1. Immediately before the `return;` on the detached-scope early-return path (line 211).
    2. Immediately before the `return;` on the already-cancelled path (line 215).
    3. Once, after `$this->cancelled = true;` (line 218), before the `foreach ($this->onCancel ...)` loop.
  - Use the null-safe operator (`?->`) defensively, since `scopeContext` is `unset` at the end of `destroy()` and any re-entrant call would otherwise NPE.
  - Verify: `composer psalm`, `composer cs:diff`. No test added here.

- [ ] **#3 (M1) — Break out of the bridge generator after `$fiber->throw` terminates the Fiber.**
  - In `Scope::createFiberHandler()` (line 479–518), modify the inner `\Generator` closure's loop body (currently line 508–510):
    ```php
    } catch (\Throwable $e) {
        $value = $fiber->throw($e);
        if ($fiber->isTerminated()) {
            break;
        }
    }
    ```
  - Add a one-line PHPDoc above the closure explaining that re-awaiting an already-settled promise after a caught injection is undefined and the bridge breaks to avoid yielding stale values. **One line only** — per `CLAUDE.md` "Comments" section, no essays.
  - Verify: `composer psalm`, `composer cs:diff`.

### Group 2 — Design decisions (spikes first)

- [ ] **#4 (SPIKE-M9) — Verify safety of throwing `DestructMemorizedInstanceException` into the bridge from `destroy()`.**
  - **Goal:** establish whether `$this->coroutine->throw(new DestructMemorizedInstanceException())` inside `Scope::destroy()` is safe under all known call sites of `destroy()`.
  - **Approach:**
    1. `rtk grep -rn 'scope->destroy(\|->destroy()' src/` and `rtk grep -rn '::destroy()' src/Internal/Workflow/` — enumerate all callers of `Scope::destroy`.
    2. Read each caller. Identify whether any caller is already inside the `Scope::next()` driving loop (i.e. re-entrant destroy: the bridge throws → user `finally` calls a workflow API → which triggers another `destroy()`).
    3. Read `DeferredGenerator::throw()` and `handleException()` (already-known `never`-returning) — verify the bridge's `finally` runs in this path and that subsequent `unset($this->coroutine)` is a no-op on an already-completed iterator.
    4. Trace the `DestructMemorizedInstanceException` propagation path: it goes through the bridge generator's `try { } finally { setFiberMode(false); }`, through `DeferredGenerator::handleException()` (which calls registered catchers then rethrows), and finally reaches the outer `Scope::destroy()` try/catch wrapper.
  - **Deliverable:** a written note appended to the implementation log of this plan, structured as:
    - Caller list with re-entrancy classification (safe / re-entrant / unknown).
    - Recommended shape: either (a) throw with a guarded `try/catch (\Throwable) {}` swallowing wrapper, or (b) abort the M9 fix and downgrade to a `TODO(plan-or-issue-ref)` pointing at a follow-up plan.
    - Concrete diff sketch for whichever shape was chosen.
  - **Stop condition:** if the spike reveals more than one re-entrant caller, downgrade M9 to docs-only and skip task #5.

- [ ] **#5 (M9) — Throw `DestructMemorizedInstanceException` into the bridge before unsetting `$this->coroutine`.**
  - **Conditional on SPIKE-M9 outcome.** If the spike recommended the throw approach:
    - In `Scope::destroy()`, immediately after `$this->scopeContext?->setFiberMode(false);` (added in task #2) and before `unset(...)`, add:
      ```php
      if (isset($this->coroutine) && $this->coroutine->isRunning()) {
          try {
              $this->coroutine->throw(new DestructMemorizedInstanceException());
          } catch (\Throwable) {
              // Bridge generator's finally still runs; further failure here is harmless.
          }
      }
      ```
    - The bare swallowing `catch` is acceptable here because (a) `destroy()` is itself a cleanup path with no caller expecting a return value, (b) any escape would defeat the cleanup goal, and (c) `DeferredGenerator::handleException` will already have notified registered `catchers` before rethrowing.
    - The comment is **exempt** from the "no comments" rule under CLAUDE.md's narrow allowance for "a hidden constraint a reader genuinely cannot recover from the code". One line only.
  - If the spike recommended docs-only:
    - Add a one-line note to the class docblock (alongside the L1 note): *"`Scope::destroy()` does not run user-side Fiber `try/finally` blocks; see `TODO(<issue>)`."* with a real follow-up plan reference (create the plan stub if needed and reference its filename).
  - Verify: `composer psalm`, `composer cs:diff`. No test here.

- [ ] **#6 (SPIKE-M3) — Verify reflection-based Generator detection works across all handler shapes.**
  - **Goal:** confirm `\ReflectionFunction::isGenerator()` (or equivalent) reliably classifies all real handler shapes that flow through `createCoroutine()`.
  - **Approach:**
    1. Enumerate all call sites of `createCoroutine` (currently `Scope::start` and `Scope::callSignalOrUpdateHandler`). Note that the latter wraps the user handler in an anonymous closure — reflection must see through the wrapper.
    2. `rtk grep -rn 'startUpdate\|startSignal\|->start(' src/Internal/` — enumerate how handlers reach `createCoroutine`.
    3. Test reflection against three handler shapes:
       - A `\Closure` directly bound to a method that uses `yield`. Verify `(new \ReflectionFunction($closure))->isGenerator()` returns `true`.
       - A `MethodHandler` instance (see `src/Internal/Declaration/MethodHandler.php`). Determine whether `MethodHandler` exposes the underlying reflection, and if not, whether it implements `__invoke` in a way that `\ReflectionMethod` can introspect.
       - The closure wrapper added by `callSignalOrUpdateHandler` (line 361–368). Since the wrapper itself is `static function (...) use ($handler) { return $handler(...); }`, the *wrapper* is not a generator even if `$handler` is. Reflection on the wrapper returns `false` — but the wrapper still wraps `$handler($values)`, which could be a generator. The bridge already handles this: `DeferredGenerator::start()` line 205–208 checks `if ($result instanceof \Generator)` after invocation. So the M3 fast-path can either (a) introspect through the wrapper (likely impossible without bespoke code) or (b) accept that signal/update handlers always pay the Fiber wrap cost.
    4. Decide whether the M3 perf benefit justifies the detection complexity. The main `start()` call site is the hot path for typical workflows; signal/update paths are colder.
  - **Deliverable:** written note appended to the implementation log with:
    - Per-handler-shape verdict (detectable / not detectable / detectable-but-expensive).
    - Recommended detection mechanism. If `MethodHandler` exposes a `ReflectionMethod` accessor: use it. If not: either add a `MethodHandler::isGenerator(): bool` accessor (small one-line change to a single class — acceptable scope creep, document in the plan log) or punt.
    - Concrete diff sketch.
  - **Stop condition:** if reflection cannot reliably classify the main `Scope::start` call site, downgrade M3 to a deferred follow-up plan and skip task #7.

- [ ] **#7 (M3) — Skip Fiber wrap for Generator-returning handlers.**
  - **Conditional on SPIKE-M3 outcome.** If the spike recommended detection:
    - In `Scope::createCoroutine()` (line 470), branch on the detection result:
      ```php
      if ($this->isGeneratorHandler($handler)) {
          return DeferredGenerator::fromHandler($handler, $values)
              ->catch($this->onException(...));
      }

      $fiberHandler = $this->createFiberHandler($handler, $this->scopeContext);
      return DeferredGenerator::fromHandler($fiberHandler, $values)
          ->catch($this->onException(...));
      ```
    - Add the `isGeneratorHandler(callable $handler): bool` private method using the reflection mechanism from the spike.
    - If a `MethodHandler::isGenerator()` accessor was required, add it as part of this task and note the scope creep in the commit message and implementation log.
  - If the spike recommended punting: skip this task. M3 is deferred to a follow-up plan, no `Scope.php` change.
  - Verify: `composer psalm`, `composer cs:diff`. No test here.

- [ ] **#8 (M4) — Remove or annotate redundant `setCurrentContext` inside Fiber body.**
  - **Conditional on task #7 outcome** — if M3 routes Generator handlers around the Fiber wrap, the surviving Fiber-body `setCurrentContext` line is the only place that sets the context for the Fiber-mode path. The trace below must consider both M3 outcomes.
  - **Approach (behaviour trace):**
    1. Read `Scope::start()` → `Scope::next()` → `Scope::makeCurrent()` (line 406–409). Confirm `makeCurrent()` is invoked before the coroutine is driven for the first time (it is — `next()` line 413).
    2. The bridge `\Generator` body **does not** itself call `makeCurrent` — only the **Fiber body inside the bridge** does (line 484).
    3. Question: between the outer `makeCurrent()` (line 413) and the Fiber body running (line 481, called via `DeferredGenerator::start()` which calls the handler), can the current context change? Trace `Workflow::setCurrentContext` callers. The answer is "yes if any callback or sub-scope ran between line 413 and the Fiber starting" — but `Scope::next()` calls `$this->coroutine->isRunning()` which lazy-starts the `DeferredGenerator`, which invokes the handler closure, which creates and starts the Fiber. **Within that synchronous call chain, no other scope can run.** So line 484 is redundant on the first call. However, on resumption after suspension, the bridge `yield` returns, `Scope::next()` runs (with `makeCurrent()` at line 413), and the Fiber is resumed via `$fiber->resume()` — at which point the Fiber-body code after `Fiber::suspend()` runs, **not** the line 484 code. So line 484 only ever runs once, on Fiber start.
    4. Conclusion: line 484 is redundant given the outer `makeCurrent()` chain. Safe to delete.
  - **Action:**
    - If trace confirms redundancy (expected): delete line 484 entirely.
    - If trace surfaces a case where the outer `makeCurrent()` is bypassed (unexpected): keep line 484 and add a one-line PHPDoc above the Fiber closure explaining the surviving case. One line.
  - Verify: `composer psalm`, `composer cs:diff`. Run `composer test:unit` to ensure no unit test depends on the removed call (none should — it is a setter).

### Group 3 — Style / docs

- [ ] **#9 (H9) — Convert short-circuit `and` at line 611 to `if`.**
  - In `Scope::defer()` (line 608), replace:
    ```php
    $this->services->queue->count() === 0 and $this->services->loop->tick();
    ```
    with:
    ```php
    if ($this->services->queue->count() === 0) {
        $this->services->loop->tick();
    }
    ```
  - Per CLAUDE.md `### Control flow: prefer if-statements over short-circuit side effects`.
  - Verify: `composer psalm`, `composer cs:diff`.

- [ ] **#10 (L2) — Resolve and delete TODO at line 443.**
  - Delete the `// todo ->context or ->scopeContext?` comment on line 443.
  - Rationale recorded once in the implementation log (not in the source file): the next case (`RequestInterface`) uses `$this->scopeContext` which is correct — per-scope context owns cancellation, so the request must be attached to the same context for cancel propagation to work. There is no surviving question to defer.
  - Verify: `composer cs:diff` only.

- [ ] **#11 (L1) — Document detached-scope semantics on the class.**
  - In the `Scope` class-level docblock (lines 34–40), append a one-sentence note before the `@internal` line: *"Detached scopes (see `isDetached`) early-return from `cancel()` unless the reason is a `DestructMemorizedInstanceException`; their cleanup is driven by the memory-flush path."*
  - Do **not** add a multi-paragraph essay. Per CLAUDE.md `### Comments`: one line capturing a hidden constraint.
  - Verify: `composer cs:diff`.

## Verification

After all 11 tasks land in the worktree, run **in order**:

1. `composer cs:diff` — must report no style violations.
2. `composer psalm` — must report no new errors (baseline unchanged or with explicit additions).
3. `composer test:unit` — must pass (Scope is exercised indirectly by every unit test that constructs a workflow runtime; failures here indicate a Group 1 regression).
4. `composer test:arch` — must pass (architecture constraints unchanged).
5. **Smoke** `composer test:accept-fast` — fast acceptance suite must pass. This is the only end-to-end signal we can take in this plan; deeper regression coverage is owned by **P12**.

**Do NOT add or modify test files in this plan.** Every test that should exist for these fixes is enumerated in `.ai-factory/research/fibers-review.md` "Suggested test" hints and is owned by **P12 (`fibers-add-regression-tests.md`)**.

The `git diff master..HEAD -- src/Internal/Workflow/Process/Scope.php` after this plan should consist of small, targeted hunks at:
- lines 34–40 (L1 docblock),
- lines 207–225 (C6 cancel resets),
- lines 299–312 (C6 destroy reset + M9 throw or M9 docs note),
- lines 411–425 (C8 catch),
- line 443 (L2 deletion),
- line 484 (M4 deletion or annotation),
- lines 470–518 (M3 branch, if SPIKE-M3 recommends it; M1 break in the bridge),
- line 608–612 (H9 if-statement).

No other lines should move. A reviewer should be able to map each hunk to a single Group/task number.

## Follow-up dependencies

- **P12 (`fibers-add-regression-tests.md`) must follow this plan.** Each of C6, C8, M1, M9, M3, M4 needs a regression test, and the test specifics are listed in the review document (see "Suggested test" entries for each finding). P12's tasks should be drafted against the **post-fix** code shape produced by this plan.
- **C7** (force `fiberMode=false` in `Process::setQueryExecutor`) is a sibling fix in `Process.php`; it can land in parallel with this plan because the files do not overlap. However, the C7 plan's regression test (a Fiber-mode workflow with a query handler) implicitly depends on C6 being correct, so if both plans land, this one should merge first.

## Parallelization summary

- **Within this plan:** strictly sequential, single worktree, single file.
- **Against other Fiber-fix plans:** safe to parallelize with every other plan in the set (none currently touch `Scope.php`).
- **Against P12:** P12 can be drafted in parallel but must merge after this plan.
