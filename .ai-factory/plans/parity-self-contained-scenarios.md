# Plan: Self-contained Parity scenarios — relocate fixture-generators into Parity

**Branch:** nexus
**Date:** 2026-05-08
**Mode:** full (no branch — `git.create_branches: false`)
**Type:** refactor + relocation

## Settings

- Testing: yes — `tests/Parity/Nexus/HistoryParity/HistoryParityTest.php` is the green-light gate
- Logging: verbose — keep `[parity-*]` prefixes in shell scripts; PHPUnit test continues to honor `PARITY_DEBUG=1`
- Docs: yes — update `tests/Parity/README.md` "Adding a new parity scenario" section to reflect the new self-contained shape
- Roadmap linkage: skipped (no roadmap)

## Why this changes from the previous plan

The previous plan landed the Parity tier as a **comparison-only** layer that delegated fixture capture to a still-tracked Acceptance scenario via `$(MAKE) -C ../../../Acceptance/...`. The user's intent is the opposite: every Parity scenario folder owns *all* of its code — Java sources, PHP fixture-generator test, scripts, Makefile, comparison test, fixtures — so that "we only ever compare JSONs" across SDKs.

So this plan **moves** `tests/Acceptance/Extra/Nexus/HistoryParity/{Makefile,scripts,java,php}` into `tests/Parity/Nexus/HistoryParity/`, alongside the existing `HistoryParityTest.php` (the framework-driven comparison test that already lives there). The old Acceptance copy is removed entirely (it's currently staged, not committed — moving means unstaging + relocating, no commit-history rewrite needed).

## Coupling that needs handling during the move

The PHP fixture-generator (`PhpFailingCallerTest.php`) is not a vanilla PHPUnit test:

1. It extends `Temporal\Tests\Acceptance\App\TestCase` — so the **Acceptance bootstrap** must run before it (Temporal server, RoadRunner, gRPC client, `RuntimeBuilder::createEmpty` registering namespace→worker-dir maps).
2. The framework auto-derives the **task-queue name from the test's PHP namespace** (see `src/.../RuntimeBuilder.php:115` — `taskQueue: $namespace`). Renaming the namespace from `Temporal\Tests\Acceptance\Extra\Nexus\HistoryParity\Php` → `Temporal\Tests\Parity\Nexus\HistoryParity\Php` therefore **changes the task queue**, which the Java caller in `Makefile` passes via `--task-queue` — so the Makefile's `TASK_QUEUE` variable and the Nexus endpoint registration must move in lockstep.
3. `tests/runner.php` heuristically picks a bootstrap from the path token after `tests/` (regex `\btests/(\w+)/`). For a path like `tests/Parity/Nexus/HistoryParity/php/PhpFailingCallerTest.php` it picks `Parity`, which would `include tests/Parity/bootstrap.php` — but that bootstrap is the **lightweight comparison-mode** one (PSR-4 only). The fixture-generator needs the heavy Acceptance bootstrap.
4. The Acceptance bootstrap explicitly registers `Temporal\Tests\Acceptance\Harness` and `Temporal\Tests\Acceptance\Extra` as worker namespace→directory pairs. To make a fixture-gen test under `Temporal\Tests\Parity\…` discoverable, that map needs an additional entry pointing into `tests/Parity/`.

## Approach

**Single-bootstrap path:** extend the Acceptance bootstrap with one additional namespace→dir entry (`Temporal\Tests\Parity` → `tests/Parity`) and route Parity fixture-generator paths through that bootstrap from `tests/runner.php`. This keeps the change to tracked code minimal (two files, ~3 lines), avoids duplicating the ~150-line Acceptance bootstrap, and still leaves the Parity comparison-mode path (`vendor/bin/phpunit -c tests/Parity/phpunit.xml`) using its own lightweight bootstrap.

`tests/Parity/` remains gitignored, so an entry like `'Temporal\Tests\Parity' => __DIR__ . '/../Parity'` in the Acceptance bootstrap will simply have nothing to scan when the directory doesn't exist (RuntimeBuilder iterates the dir lazily; missing paths just yield no classes — safe). If a guard is needed, add `is_dir(...)` filtering at the bootstrap site.

## Target layout

```
tests/Parity/Nexus/HistoryParity/             ← self-contained scenario folder
├── Makefile                                  ← drives the full capture (re-anchored, new TASK_QUEUE)
├── HistoryParityTest.php                     ← already there: framework-driven assertEquals
├── scripts/
│   ├── setup.sh                              ← unchanged
│   ├── run-java.sh                           ← unchanged
│   └── run-php.sh                            ← updated: invokes phpunit with file-path, not --testsuite=Acceptance
├── java/                                     ← unchanged Gradle project (just moved)
├── php/
│   └── PhpFailingCallerTest.php              ← namespace bumped to Temporal\Tests\Parity\Nexus\HistoryParity\Php
└── fixtures/
    ├── .gitkeep
    └── *.json                                ← captured outputs

tests/Acceptance/Extra/Nexus/HistoryParity/   ← removed entirely after the move
```

## Tasks

Progress:

- [x] **#9** Move HistoryParity filesystem assets from Acceptance into Parity (unstage + mv + cleanup)
- [x] **#10** Bump PhpFailingCallerTest namespace to `Temporal\Tests\Parity\…`
- [x] **#11** Re-anchor scenario Makefile (PROJECT_ROOT 4-ups, new TASK_QUEUE, drop `compare` target)
- [x] **#12** Rewrite run-php.sh to invoke phpunit by file path
- [x] **#13** Extend `tests/Acceptance/bootstrap.php` to discover Parity worker paths (added `is_dir` guard for fresh checkouts)
- [x] **#14** Route Parity fixture-generator paths through Acceptance bootstrap in `tests/bootstrap.php` (the suite-routing layer; corrected from plan's mention of `tests/runner.php`)
- [x] **#15** End-to-end verify: capture fresh fixtures and assert parity (Phase 1 OK after extending `tests/Acceptance/worker.php` namespace map; Phase 2 OK after excluding `*/php/` from `tests/Parity/phpunit.xml`; framework diff matches previous plan run — no relocation regression)
- [x] **#16** Update `tests/Parity/README.md` for self-contained scenario layout (layout, "Adding a scenario" walkthrough, and a new "Bootstrap routing" subsection covering the suite-router + Acceptance-bootstrap + worker.php + phpunit.xml-exclusion wiring)

1. **#1 Move filesystem assets from Acceptance into Parity**
   - `git rm --cached -r tests/Acceptance/Extra/Nexus/HistoryParity/` (unstage; files are currently `A`/`AM` from the in-progress branch, not committed)
   - `mv tests/Acceptance/Extra/Nexus/HistoryParity/{Makefile,scripts,java,php} tests/Parity/Nexus/HistoryParity/`
   - Delete `tests/Acceptance/Extra/Nexus/HistoryParity/HistoryParityTest.php` (superseded by the framework-driven test already in Parity)
   - Delete the now-empty `tests/Acceptance/Extra/Nexus/HistoryParity/fixtures/` (any captured JSONs from earlier runs are also under Parity once a fresh capture happens — old copies aren't load-bearing)
   - Remove the now-empty `tests/Acceptance/Extra/Nexus/HistoryParity/` directory itself
   - **Logging:** print each `mv`/`rm` step so the relocation is auditable

2. **#2 Update `tests/Parity/Nexus/HistoryParity/php/PhpFailingCallerTest.php` namespace**
   - Change `namespace Temporal\Tests\Acceptance\Extra\Nexus\HistoryParity\Php;` → `namespace Temporal\Tests\Parity\Nexus\HistoryParity\Php;`
   - No other code changes — the test body is namespace-agnostic
   - **Logging:** none (data-only edit)

3. **#3 Re-anchor the scenario `Makefile` and refresh task-queue / endpoint identity**
   - `PROJECT_ROOT := $(abspath ../../../../..)` (5 ups, was correct in original Acceptance location at depth 5; new location `tests/Parity/Nexus/HistoryParity` is depth 4 → `$(abspath ../../../..)`)
   - `TASK_QUEUE := Temporal\Tests\Parity\Nexus\HistoryParity\Php` (matches the relocated PHP namespace)
   - `NAMESPACE` and `ENDPOINT` can stay as-is (`nexus-parity` / `nexus-parity-failing-endpoint`) — they're scenario-scoped strings, not derived from PHP code
   - Replace the current Parity scenario Makefile (which delegates upstream) with the **moved** scenario Makefile from Acceptance, edited as above. The delegating Makefile from the previous plan is dropped.
   - **Logging:** `@echo` lines stay in `==> setup` / `==> run-java` / `==> run-php` style

4. **#4 Update `tests/Parity/Nexus/HistoryParity/scripts/run-php.sh` to invoke phpunit with file-path**
   - The original used `--testsuite=Acceptance --filter=PhpFailingCallerTest`. The relocated test is no longer registered in any `phpunit.xml.dist` testsuite, so file-path invocation is required.
   - New invocation: `tests/runner.php vendor/bin/phpunit "tests/Parity/Nexus/HistoryParity/php/PhpFailingCallerTest.php" --testdox`
   - The script's existing `cd "$PROJECT_ROOT"` block stays — phpunit is invoked from project root so the file path is relative to that
   - Pass the new `WORKFLOW_TYPE` value if it changed (it didn't — `Extra_Nexus_HistoryParity_FailingCaller` is set inside the test body, not derived from path)
   - **Logging:** keep `set -euo pipefail` and `echo` status lines

5. **#5 Extend the Acceptance bootstrap to discover Parity worker paths**
   - Edit `tests/Acceptance/bootstrap.php` line 37–40:
     ```php
     $runtime = RuntimeBuilder::createEmpty($environment->command, \getcwd(), [
         'Temporal\Tests\Acceptance\Harness' => __DIR__ . '/Harness',
         'Temporal\Tests\Acceptance\Extra' => __DIR__ . '/Extra',
         'Temporal\Tests\Parity' => __DIR__ . '/../Parity',  // ← new entry
     ], workers: ...);
     ```
   - Wrap the new entry with `array_filter`/`is_dir` if `RuntimeBuilder` doesn't already tolerate missing dirs (verify by reading `tests/Acceptance/App/Runtime/State.php` or `RuntimeBuilder::iterateClasses`). If it does — skip the guard, the lazy iteration handles it. If it doesn't — add a one-line guard at the bootstrap site so fresh checkouts (without the gitignored `tests/Parity/`) still work.
   - **Logging:** none — bootstrap is silent by convention

6. **#6 Route Parity fixture-generator paths through the Acceptance bootstrap in `tests/runner.php`**
   - Current logic: regex `\btests/(\w+)/` extracts `Parity`, which leads to `include tests/Parity/bootstrap.php` (the comparison-only one). For fixture generation we need the Acceptance bootstrap.
   - Cleanest fix: add a small post-detection mapping in `tests/runner.php` — after `$suite` is resolved, if `$suite === 'Parity'` and the path being run includes a `/php/` segment (or a `<scenario>/php/` shape), remap `$suite` to `'Acceptance'`. Comparison tests under `tests/Parity/Nexus/<Scenario>/<Scenario>Test.php` (no `/php/` segment) stay routed to the lightweight Parity bootstrap.
   - Alternative (simpler but heavier): unconditionally route `Parity` to `Acceptance` bootstrap. Rejected because it would launch the Temporal server + RoadRunner for every `make assert` invocation, which is what the comparison phase explicitly avoids.
   - **Logging:** print `[runner] Parity fixture-generator path detected — using Acceptance bootstrap` when remapping fires

7. **#7 Re-run the scenario end-to-end and verify**
   - Drop existing `tests/Parity/Nexus/HistoryParity/fixtures/*.json` (they were captured under the old namespace/task-queue and may not match)
   - Run `cd tests/Parity/Nexus/HistoryParity && make all` (or `tests/Parity` `make all`)
   - Phase 1 should: start dev server (idempotent), upsert namespace + endpoint, drive Gradle Java caller, drive PHPUnit PHP caller, dump both histories
   - Phase 2 (`make assert`) should: load both fixtures, normalize per-SDK, produce the same actionable diff as the previous plan run (proving the framework still works after the move)
   - **Logging:** capture the Make output; if the diff signals a NEW unrelated divergence, that's a regression introduced by the relocation — root-cause before marking done

8. **#8 Update README — "Adding a new parity scenario" + remove the "delegate to Acceptance" pattern**
   - Section "Adding a new parity scenario" currently shows a delegating Makefile snippet. Replace with a **self-contained** template (own setup/run-java/run-php scripts inside the scenario).
   - Update the Layout section's `Nexus/HistoryParity/` block to list `scripts/`, `java/`, `php/` as scenario-local
   - Add a short "Bootstrap routing" note explaining the Acceptance-bootstrap override in `tests/runner.php` so future scenario authors aren't surprised
   - **Logging:** doc only

## Risks & open questions

- **Java Gradle cache files were accidentally staged on this branch** (`.gradle/8.10.2/...`). The relocation will move those too. Out of scope for this plan; flag a follow-up cleanup that adds `tests/Parity/**/.gradle/` to `.gitignore` (already covered by the broader `tests/Parity/` ignore) and removes any stray staged `.gradle/` paths from the index. **Action:** during Task #1, also `git rm --cached -r` the staged `tests/Acceptance/Extra/Nexus/HistoryParity/java/.gradle/` artifacts so they don't leak into a future commit.
- **`RuntimeBuilder` tolerance for missing directories** is not verified yet. Task #5 includes the verification step — if it doesn't tolerate missing dirs, a one-line `is_dir` guard is added at the bootstrap site.
- **First post-move fixture capture is the gate**. If Java/PHP can't both register on the new task-queue (`Temporal\Tests\Parity\Nexus\HistoryParity\Php`), the run will hang. Task #7 should set a sensible Phase-1 timeout (the existing `tests/Parity/run-fixtures.sh` already wraps `make all` with `timeout 600`). Investigate the `nexus-parity` namespace state if Phase 1 fails: `temporal --namespace nexus-parity workflow list` from the project root.
- **No backwards-compat bridge** is provided for the old `tests/Acceptance/Extra/Nexus/HistoryParity/` location. Since the original copy was never committed (only staged on the in-progress `nexus` branch), removing it is a clean operation, not a breaking one.

## Commit Plan

5+ tasks → checkpoints every ~3 tasks:

- **Commit 1 (after #1, #2, #3, #4):** `refactor(tests/parity): make HistoryParity scenario self-contained`
  - filesystem move + namespace bump + Makefile re-anchor + run-php.sh rewrite
- **Commit 2 (after #5, #6):** `feat(tests): route Parity fixture-generator tests through Acceptance bootstrap`
  - tracked-code change: bootstrap namespace map + runner.php detection
- **Commit 3 (after #7, #8):** `docs(tests/parity): update README for self-contained scenario layout`

Note: `tests/Parity/` itself is gitignored, so Commits 1 and 3 will surface only as `.gitignore`-adjacent state (no diff). Commit 2 is the only one with substantive tracked-code changes (two files: `tests/Acceptance/bootstrap.php` and `tests/runner.php`). All commits should also drop the previously-staged `tests/Acceptance/Extra/Nexus/HistoryParity/*` paths from the index.

## Next Steps

After review, run `/aif-implement` to execute. The dependency graph is mostly linear (#1 → #2 → #3 → #4 in lockstep, then #5 + #6 in parallel, then #7 → #8). Limited parallelization opportunity since most tasks touch overlapping files.

Plan file: `.ai-factory/plans/parity-self-contained-scenarios.md`
