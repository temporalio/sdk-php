# Plan: Remove debug `$a=1;` and revert cosmetic split in tests/Acceptance/worker.php

**Branch:** fibers
**Date:** 2026-05-23
**Mode:** fast
**Type:** hygiene / revert
**Source finding:** `.ai-factory/research/fibers-review.md` — C1 (CRITICAL, lines 43–50)

## Settings

- Testing: yes — smoke-verify the acceptance worker still boots
- Docs: no
- Logging: minimal — single-file revert, no runtime logic changed
- Roadmap linkage: none

## Scope statement

This plan touches `tests/Acceptance/worker.php` only — safe to parallelize with all other Fiber-fix plans.

## Finding

`git diff master..HEAD -- tests/Acceptance/worker.php` shows exactly one hunk added by the `fibers` branch:

```
@@ -119,7 +119,10 @@ try {
-    $container->get(WorkerFactoryInterface::class)->run();
+    $factory = $container->get(WorkerFactoryInterface::class);
+    $factory->run();
 } catch (\Throwable $e) {
     td($e);
 }
+
+$a=1;
```

Two distinct issues in one hunk:

1. **Debug leftover (`$a=1;` at line 128).** Functionally harmless — the assignment is at top level after the `try/catch` and has no observers — but it is a clear "uncommitted scratch" signal and was flagged as a CRITICAL hygiene finding (C1) in the consolidated review.
2. **Cosmetic 2-line split of `$container->get(WorkerFactoryInterface::class)->run();`** into a local `$factory` binding followed by `$factory->run();`. There is no stated reason (no debugger pin-point, no reused reference, no readability win — the fluent call was already a single, idiomatic line). The split is pure noise and inflates the diff against `master`.

**Decision:** delete the debug line **and** revert the cosmetic split. Rationale:
- The split provides zero benefit and was almost certainly a side effect of the debug session that introduced `$a=1;`.
- Reverting both changes makes the final `git diff master..HEAD -- tests/Acceptance/worker.php` empty, which is the cleanest possible outcome for a hygiene fix on a test bootstrap file.
- If a future change genuinely needs `$factory` as a local (e.g. to install a signal handler before `run()`), it can be reintroduced with an explicit reason in its own commit.

## Target end state

After this fix, `tests/Acceptance/worker.php` lines 114–126 must read exactly:

```php
    foreach ($runtime->workflows() as $feature => $workflow) {
        $getWorker($feature)->registerWorkflowTypes($workflow);
    }

    foreach ($runtime->activities() as $feature => $activity) {
        $getWorker($feature)->registerActivityImplementations($container->make($activity));
    }

    $container->get(WorkerFactoryInterface::class)->run();
} catch (\Throwable $e) {
    td($e);
}
```

…and `git diff master..HEAD -- tests/Acceptance/worker.php` must produce no output.

## Tasks

- [x] **#1** In `tests/Acceptance/worker.php`:
  - Delete the trailing blank line and `$a=1;` (current lines 127–128).
  - Collapse the `$factory = $container->get(WorkerFactoryInterface::class); $factory->run();` pair back to the single statement `$container->get(WorkerFactoryInterface::class)->run();` matching `master`.
- [x] **#2** Verify the file:
  - `php -l tests/Acceptance/worker.php` — must report `No syntax errors detected`.
  - `rtk git diff master..HEAD -- tests/Acceptance/worker.php` — must produce **no output** (the only branch-modified hunk is now reverted).
- [x] **#3** Smoke-verify acceptance harness still boots:
  - Run `composer test:accept-fast`.
  - If the local environment cannot reach the Temporal server / RoadRunner needed for the full fast suite, the lint check in #2 plus an empty diff against `master` is sufficient evidence — `worker.php` is a thin bootstrap and the only branch-local change is being removed.

## Out of scope (explicitly)

- All other Fiber-review findings (C2–C8, every H/M/L item). Each gets its own plan.
- Any change to `master`-resident lines of `worker.php` (this is a strict revert of branch-local additions).
- Adding tests for the worker bootstrap — the review explicitly notes "Suggested test: None — purely a hygiene fix."

## Parallelization

This plan is independent of every other Fiber-fix plan because:
- It modifies exactly one file (`tests/Acceptance/worker.php`) that no other Fiber finding touches.
- It introduces zero new symbols, interfaces, or runtime behavior.
- The verification step (`composer test:accept-fast`) does not depend on any other Fiber-fix being merged first; the suite has been running with `$a=1;` already and will continue to run without it.

Safe to dispatch alongside C2–C8 plans and all H/M/L plans.
