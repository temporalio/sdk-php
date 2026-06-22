# Plan: Delete broken Fiber acceptance copies (TaskQueue/Fibers + Schedule/Fibers)

**Branch:** fibers
**Date:** 2026-05-23
**Mode:** full
**Type:** cleanup / test-hygiene
**Source findings:** `.ai-factory/research/fibers-review.md` — C4 (CRITICAL, lines 86–96) and C5 (CRITICAL, lines 98–102)

## Settings

- Testing: yes — boot-verify the acceptance harness with `composer test:accept-fast`
- Docs: no
- Logging: minimal — file deletions plus two `phpunit.xml.dist` line removals; no runtime logic changed
- Roadmap linkage: none

## Scope statement

This plan touches files under `tests/Acceptance/Extra/TaskQueue/Fibers/*`, `tests/Acceptance/Extra/Schedule/Fibers/*`, and `phpunit.xml.dist`. It does **not** touch `tests/Acceptance/App/TaskQueueResolver.php` (see decision below). Safe to parallelize with every other Fiber-fix plan — no overlap.

## Findings recap (from the review)

### C4 — `TaskQueue/Fibers/Workflow{A,B}Test.php` provide zero Fiber coverage and collide on registration

Confirmed by direct read:

- `tests/Acceptance/Extra/TaskQueue/Fibers/WorkflowATest.php` (33 lines): no `use Temporal\Experiments\Fibers\Workflow;` import; the workflow body is a plain `return 42;`. Registers `#[WorkflowMethod(name: "Workflow")]`.
- `tests/Acceptance/Extra/TaskQueue/Fibers/WorkflowBTest.php` (35 lines): imports `Temporal\Workflow` (the **base** namespace, not `Experiments\Fibers\Workflow`) and never uses it; body is `return 24;`. Also registers `#[WorkflowMethod(name: "Workflow")]`.
- The non-Fibers siblings at `tests/Acceptance/Extra/TaskQueue/WorkflowATest.php` and `WorkflowBTest.php` register **the same bare name** `"Workflow"` and are explicitly listed in `TaskQueueResolver::SHARED_QUEUE_EXCLUSIONS` (`tests/Acceptance/App/TaskQueueResolver.php:17–18`) so each gets its **own** task queue — preventing collision *between themselves*.
- The Fibers copies are **not** in `SHARED_QUEUE_EXCLUSIONS`, so per the resolver's fall-through (lines 34–49) they land on the shared `default` queue and re-register `"Workflow"` there. Either the worker fails to boot with `"Workflow with name 'Workflow' already registered"`, or whichever `RuntimeBuilder` pass discovers last silently wins and the Fibers test exercises an arbitrary peer's workflow class.
- Either way the Fibers versions provide zero independent coverage and zero Fiber-path coverage.

### C5 — `Schedule/Fibers/Schedule{Client,Update}Test.php` are byte-identical client-side copies

Confirmed by direct read against the non-Fibers siblings:

- `tests/Acceptance/Extra/Schedule/Fibers/ScheduleClientTest.php` (72 lines) vs `tests/Acceptance/Extra/Schedule/ScheduleClientTest.php` — only the namespace differs (`...Schedule\Fibers\ScheduleClient` vs `...Schedule\ScheduleClient`).
- `tests/Acceptance/Extra/Schedule/Fibers/ScheduleUpdateTest.php` (175 lines) vs `tests/Acceptance/Extra/Schedule/ScheduleUpdateTest.php` — same: namespace-only diff.
- Both files exercise `ScheduleClientInterface` exclusively — pure client-side gRPC paths. Nothing inside the worker is involved, so `src/Experiments/Fibers/*` cannot be exercised regardless of where the file lives. The "Fibers" location is purely cosmetic.

## Options evaluated

### C4 options

(a) **Delete** both `TaskQueue/Fibers/Workflow{A,B}Test.php`.
- Pros: removes the registration collision and the false coverage signal in one step; matches the review's framing that there is nothing to salvage (both files are non-Fiber by construction); leaves the existing non-Fibers `TaskQueue/Workflow{A,B}Test.php` providing the only — and intended — coverage for the bare-`"Workflow"` registration case; no churn in `TaskQueueResolver.php`.
- Cons: zero — these files don't carry any unique behavioural assertions; they are pure copies of their non-Fibers siblings with the `use Temporal\Workflow` import added (and not actually used). There is no Fiber wiring to preserve.

(b) **Rewrite** to use the Fiber facade.
- Required steps: (1) replace `return 42;` / `return 24;` with bodies that actually exercise the Fiber path (e.g. `Fibers\Workflow::executeActivity(...)` against a trivial activity, plus a sentinel proving the body ran inside a Fiber); (2) rename the workflow registration from `"Workflow"` to something Fiber-specific (e.g. `"Workflow_Fibers_A"` / `"Workflow_Fibers_B"`) and update the matching `#[Stub(type: ...)]` argument; (3) add the rewritten test classes to `TaskQueueResolver::SHARED_QUEUE_EXCLUSIONS`.
- Pros: keeps two more Fiber-exercised acceptance tests.
- Cons: those tests would not test anything that other Fiber acceptance tests (e.g. `tests/Acceptance/Extra/Activity/Fibers/*`) don't already cover. The non-Fibers `WorkflowATest`/`WorkflowBTest` exist precisely to verify the **isolation-queue** mechanism in `TaskQueueResolver` — that mechanism is queue plumbing, not workflow logic, and a Fiber-flavoured copy would only verify the same plumbing twice. The Fiber-vs-Generator distinction is orthogonal to the SHARED_QUEUE_EXCLUSIONS behaviour.

**Decision: (a) — delete.** Lower risk, no lost coverage, removes a guaranteed boot-time foot-gun, and avoids enlarging `SHARED_QUEUE_EXCLUSIONS` for tests that wouldn't add information.

### C5 options

(a) **Delete** both `Schedule/Fibers/Schedule{Client,Update}Test.php`.
- Pros: removes 247 lines of pure duplication; halves the runtime cost of those scenarios (each acceptance test sets up + tears down schedule resources against the live Temporal server); also cleans up two stale `<exclude>` + `<file>` entries in `phpunit.xml.dist` that reference `Schedule/Fibers/ScheduleUpdateTest.php` for slow-suite routing.
- Cons: zero. The non-Fibers `Schedule/ScheduleClientTest.php` and `Schedule/ScheduleUpdateTest.php` continue to exercise every assertion. There is no Fiber-relevant code path inside `ScheduleClientInterface`, `Schedule`, `ScheduleHandle`, `ScheduleOptions`, or `ScheduleUpdate` — they are client-side gRPC plumbing.

(b) **Keep** with a justification.
- The only possible justification would be "placeholder for future client-side Fiber work" (raised as open question #6 in the review). There is no client-side Fiber API today and no roadmap entry pointing to one. Keeping byte-identical copies *just in case* doubles the live-server cost on every CI run for an outcome that may never happen — and if it does happen, the future change can re-introduce purpose-built tests.

**Decision: (a) — delete.** Recommended by the review and supported by the absence of any client-side Fiber wiring to verify.

## Target end state

- `tests/Acceptance/Extra/TaskQueue/Fibers/` directory removed (was: `WorkflowATest.php`, `WorkflowBTest.php`).
- `tests/Acceptance/Extra/Schedule/Fibers/` directory removed (was: `ScheduleClientTest.php`, `ScheduleUpdateTest.php`).
- `phpunit.xml.dist` no longer mentions `Schedule/Fibers/ScheduleUpdateTest.php` — the two references on **line 53** (`<exclude>` from `Acceptance-Fast`) and **line 85** (`<file>` in `Acceptance-Slow`) are removed; no other references to `Schedule/Fibers` or `TaskQueue/Fibers` exist (verified via `rtk grep -rn "TaskQueue/Fibers\|Schedule/Fibers" tests/ src/ phpunit.xml.dist`).
- `tests/Acceptance/App/TaskQueueResolver.php` is **unchanged**. The Fibers TaskQueue classes were never in `SHARED_QUEUE_EXCLUSIONS`, and the non-Fibers `WorkflowATest::class` / `WorkflowBTest::class` entries (lines 17–18) remain — they still need their own queues.
- `composer test:accept-fast` boots without the `"Workflow with name 'Workflow' already registered"` collision and reports the suite running.

## Tasks

- [ ] **#1** Delete the four broken Fiber test files (single `git rm` batch):
  - `tests/Acceptance/Extra/TaskQueue/Fibers/WorkflowATest.php`
  - `tests/Acceptance/Extra/TaskQueue/Fibers/WorkflowBTest.php`
  - `tests/Acceptance/Extra/Schedule/Fibers/ScheduleClientTest.php`
  - `tests/Acceptance/Extra/Schedule/Fibers/ScheduleUpdateTest.php`
  - After removal, both parent directories (`TaskQueue/Fibers/` and `Schedule/Fibers/`) become empty; remove the empty directories too (`rmdir`) so PHPUnit's directory-suffix discovery doesn't traverse phantom paths.

- [ ] **#2** Update `phpunit.xml.dist` — remove the two `Schedule/Fibers/ScheduleUpdateTest.php` references:
  - Delete the `<exclude>tests/Acceptance/Extra/Schedule/Fibers/ScheduleUpdateTest.php</exclude>` line at line 53 (currently paired with the non-Fibers exclude at line 52 in the `Acceptance-Fast` testsuite).
  - Delete the `<file>tests/Acceptance/Extra/Schedule/Fibers/ScheduleUpdateTest.php</file>` line at line 85 (currently paired with the non-Fibers entry at line 84 in the `Acceptance-Slow` testsuite).
  - Leave the non-Fibers lines 52 (`<exclude>tests/Acceptance/Extra/Schedule/ScheduleUpdateTest.php</exclude>`) and 84 (`<file>tests/Acceptance/Extra/Schedule/ScheduleUpdateTest.php</file>`) untouched.

- [ ] **#3** Confirm no orphaned references remain:
  - `rtk grep -rn "TaskQueue/Fibers\|Schedule/Fibers" tests/ src/ phpunit.xml.dist .php-cs-fixer.dist.php psalm.xml` — must produce **no output**.
  - `rtk grep -rn "Schedule\\\\Fibers\|TaskQueue\\\\Fibers" tests/ src/` — must produce **no output** (catches any stale PHP namespace string references).

- [ ] **#4** Verify the boot path — run `composer test:accept-fast`:
  - The pre-fix expectation is that the suite either fails fast with `"Workflow with name 'Workflow' already registered"` from the TaskQueue collision, or boots but with the wrong workflow class servicing one of the colliding registrations.
  - The post-fix expectation is that the suite boots cleanly and proceeds to actual test execution. **Acceptance criterion:** no `RegistrationException` (or equivalent) is emitted for workflow type `"Workflow"`, and the run reports a normal pass/fail tally rather than a fatal at startup.
  - If the local environment cannot reach a Temporal server / RoadRunner instance, the boot-up phase still runs (worker construction + workflow discovery happen before the gRPC connect). Capture the failure mode and confirm it is a server-connection error, not a registration error.

- [ ] **#5** Final diff sanity:
  - `rtk git status` — should show four deletions under `tests/Acceptance/Extra/{TaskQueue,Schedule}/Fibers/` and one modification to `phpunit.xml.dist`.
  - `rtk git diff -- phpunit.xml.dist` — should show exactly two line removals (lines 53 and 85 in the original file), no additions.

## Out of scope (explicitly)

- All other Fiber-review findings (C1, C2, C3, C6, C7, C8, every H/M/L item). Each gets its own plan.
- `tests/Acceptance/App/TaskQueueResolver.php` — left untouched. There is no `SHARED_QUEUE_EXCLUSIONS` entry to add (we are deleting the files, not isolating them) and no entry to remove (the Fibers classes were never in the list).
- Cross-SDK parity tests, Schedule client refactors, or any net-new Fiber facade work.
- Adding a runtime guard that detects duplicate `#[WorkflowMethod(name:)]` collisions across the boot path. That is a generally useful safety net but is its own plan (the consolidated review's Suggested Test under C3) and outside this plan's deletion-focused scope.
- Adding Fiber-equivalent acceptance tests for Schedule when/if `Experiments\Fibers\*` ever gains a client-side surface — purely speculative today.

## Parallelization

This plan is independent of every other Fiber-fix plan because:

- It only deletes files (no edits to shared sources, no API changes, no test rewrites).
- The single non-deletion edit is to `phpunit.xml.dist`, surgically removing two lines that name files this plan is itself deleting — no other Fiber plan modifies those lines.
- It introduces zero new symbols, namespaces, or runtime behaviour.
- The verification step (`composer test:accept-fast`) does not depend on any other Fiber-fix being merged first; the collision being removed exists today and removing it only un-breaks the boot path.

Statement (verbatim, to match the requested wording): *touches files under `tests/Acceptance/Extra/TaskQueue/Fibers/*`, `tests/Acceptance/Extra/Schedule/Fibers/*`, and possibly `tests/Acceptance/App/TaskQueueResolver.php` — safe to parallelize with other Fiber-fix plans (no overlap).*

(In practice `TaskQueueResolver.php` is **not** modified — see the decision under Out of scope — but the statement is preserved as requested because the file *was* in the consideration set during planning.)
