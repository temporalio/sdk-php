# Plan: Parity tier audit cleanup — comments, shell hardening, test clarity

**Branch:** nexus
**Date:** 2026-05-08
**Mode:** full (no branch — `git.create_branches: false`)
**Type:** cleanup / quality

## Settings

- Testing: yes — re-run `cd tests/Parity && make assert` after the comment edits to confirm nothing broke
- Logging: minimal — these are local file edits, no new runtime logic to log
- Docs: yes — README correction is part of the deliverable
- Roadmap linkage: skipped (no roadmap)

## What three parallel audits surfaced

| Surface              | Critical issues | Stylistic issues | Notes |
|----------------------|-----------------|------------------|-------|
| Framework PHP        | 0               | 6 essay-comment violations | All `declare(strict_types=1)`, type hints, naming, control-flow checks pass |
| Tests                | 0               | 2 missing-attribute + 1 docblock-clarity | **No** `markTestIncomplete`, **no** `markTestSkipped`, **no** `@group skip`, **no** flaky markers anywhere in `tests/Parity/` |
| Shell + README       | 1 (false log-prefix claim) | 1 (missing tmpdir mkdir) | All scripts use `set -euo pipefail`, all variables quoted, idempotent setup, defensive validation in run-* scripts |
| Tracked-code edits   | 0               | 0                | The `$testCasesDir` extraction is clean, `is_dir` guard is correct |
| .gitignore           | 0               | 0                | `/tests/Parity/` entry is properly placed and explained |

**No skipped or flaky tests exist in the audited surface.** The single `<exclude>` in `tests/Parity/phpunit.xml` (`<exclude>./Nexus/HistoryParity/php</exclude>`) is intentional and correct: Phase 1 fixture-generator tests must NOT be picked up by Phase 2's standalone phpunit run because they require the heavy Acceptance bootstrap. The README's "Bootstrap routing" subsection documents this. Nothing to plan there.

## Concrete findings (per file)

### 1. Comment violations (CLAUDE.md: "Default: no comments. Only when WHY is non-obvious.")

| File                                                            | Lines  | Violation                                                                  |
|-----------------------------------------------------------------|--------|----------------------------------------------------------------------------|
| `tests/Parity/Framework/NormalizerRegistry.php`                 | 23–26  | Essay opening — "Public entry-point: returns a fully-wired …"               |
| `tests/Parity/Framework/NormalizerRegistry.php`                 | 41–80  | 20-line bullet-prose block above the field-rules array                      |
| `tests/Parity/Framework/Field/TimestampNormalizer.php`          | 13–16  | Restates what the placeholder name already says                             |
| `tests/Parity/Framework/Field/TaskQueueNormalizer.php`          | 7–23   | 17-line cross-SDK-naming essay in the class docblock                        |
| `tests/Parity/Framework/Field/StackTraceNormalizer.php`         | 14–22  | 9-line description of detection markers (codify in code, not docblock)      |
| `tests/Parity/Framework/Sdk/AbstractSdkNormalizer.php`          | 14–21  | 8-line walker-strategy essay                                                |

**Keep as-is** (legitimate non-obvious WHY):
- `Field/DurationNormalizer.php` 13–17 — explains *why* values get collapsed (cross-SDK precision differences)
- `Field/SdkMetadataNormalizer.php` 12–17 — borderline, but the "drop langUsedFlags" rationale is non-obvious; auditor accepted

**Keep as-is** (intentional placeholder design):
- `Sdk/PhpSdkNormalizer.php`, `JavaSdkNormalizer.php`, `GoSdkNormalizer.php` — auditor confirmed these are valid placeholder dispatch points, not dead code; their short docblock explains the placeholder intent (which IS non-obvious — a reader might otherwise assume something is missing).

### 2. Shell-script edge cases

| File                                                  | Line | Issue                                                                                     |
|-------------------------------------------------------|------|-------------------------------------------------------------------------------------------|
| `tests/Parity/Nexus/HistoryParity/scripts/setup.sh`   | 13–17 | Writes `> /tmp/temporal-parity/server.log` without `mkdir -p` first. Fails on cold tmp.   |
| `tests/Parity/Nexus/HistoryParity/scripts/run-java.sh` | all   | No `[parity-*]` log prefix on script-emitted lines. README claims they exist.             |
| `tests/Parity/Nexus/HistoryParity/scripts/run-php.sh`  | all   | Same — no `[parity-*]` prefix on script-emitted lines.                                    |

### 3. README factual error

`tests/Parity/README.md` "Logging requirements" / "Debugging" sections describe verbose tracing prefixed with `[parity-*]`. Reality: only `run-fixtures.sh` actually emits that prefix; the per-scenario `run-java.sh` / `run-php.sh` print plain `captured WORKFLOW_ID=…` / `wrote $OUT_FILE…` without any `[parity-*]` tag.

**Resolution choice:** add the prefixes to the scripts (cheap, aligns with README and the original plan's verbose-logging requirement) rather than weaken the README.

### 4. Test improvements

**`tests/Parity/Nexus/HistoryParity/HistoryParityTest.php`:**
- The auditor flagged that `assertEquals([], [])` would silently pass if both fixtures were empty arrays. In practice `HistoryLoader::loadJson` already throws on empty/missing/malformed JSON (verified in `HistoryLoader.php` lines 15–50), so the silent-pass risk is low. Still, an explicit `assertNotEmpty($java); assertNotEmpty($php);` before the comparison is cheap insurance and documents intent.
- Missing `#[CoversClass(NormalizerRegistry::class)]` + `#[UsesClass(...)]` attributes. The "Nexus only" qualifier in CLAUDE.md scopes this convention to `tests/Nexus/Unit/`, not the Parity tier. **Decision: add them anyway**, because the framework code IS the system under test here, and the attributes generate proper coverage data when phpunit emits coverage reports — useful even when the convention doesn't strictly require it.

**`tests/Parity/Nexus/HistoryParity/php/PhpFailingCallerTest.php`:**
- The auditor's deepest finding: the workflow internally catches `NexusOperationFailure`, returns `'ok'` on success or `'unexpected:no-exception'` if the failure didn't fire. The test asserts `'ok'`. This DOES implicitly verify the exception fired (otherwise the assertion would fail with `'unexpected:no-exception'`), but it doesn't verify the exception's cause chain (ErrorType::Internal, message `"boom-msg"`, RetryBehavior::NonRetryable).
- **Decision: do NOT deepen the in-PHP assertions.** The test's purpose is to PRODUCE a fixture for cross-SDK comparison; the deep verification of the failure shape happens in the cross-SDK `assertEquals` diff in HistoryParityTest. Adding a deep `try/catch` + cause-chain inspection here would duplicate what the comparison test already does at the wire-protocol level. Instead, **clarify the docblock** to state this division of labor explicitly so a future reader doesn't try to add scenario assertions here.

## Tasks

Progress:

- [x] **#17** Trim essay comments in Framework files (5 files; deleted ~50 lines of redundant prose)
- [x] **#18** Add `mkdir -p /tmp/temporal-parity` guard in setup.sh
- [x] **#19** Add `[parity-java]` / `[parity-php]` / `[parity-setup]` log prefixes
- [x] **#20** Tighten HistoryParityTest with `assertNotEmpty` guards + 2 `#[CoversClass]` + 13 `#[UsesClass]` attributes
- [x] **#21** Clarify PhpFailingCallerTest's role in the docblock + fixed stale Acceptance path reference
- [x] **#22** End-to-end re-verify (assertion count 1→3 confirms new guards fired; same actionable diff = no regression; all lint OK)

1. **#17 Trim essay comments in Framework files**
   - Files & lines: `NormalizerRegistry.php` (23–26 + 41–80), `Field/TimestampNormalizer.php` (13–16), `Field/TaskQueueNormalizer.php` (7–23), `Field/StackTraceNormalizer.php` (14–22), `Sdk/AbstractSdkNormalizer.php` (14–21)
   - For each: collapse to ≤2 lines; keep only a one-line description if the type/class name doesn't already explain it; remove anything that restates what the next line of code does
   - Leave `Field/DurationNormalizer.php`, `Field/SdkMetadataNormalizer.php`, and the three SDK placeholder classes untouched
   - **Logging:** none (data-only edits)

2. **#18 Add `mkdir -p /tmp/temporal-parity` guard in setup.sh**
   - File: `tests/Parity/Nexus/HistoryParity/scripts/setup.sh` line 13–17
   - Insert `mkdir -p /tmp/temporal-parity` immediately before the `"$TEMPORAL" server start-dev` line that redirects into `/tmp/temporal-parity/server.log`
   - **Logging:** none

3. **#19 Add `[parity-java]` / `[parity-php]` log prefixes to scenario scripts**
   - File: `tests/Parity/Nexus/HistoryParity/scripts/run-java.sh`
   - File: `tests/Parity/Nexus/HistoryParity/scripts/run-php.sh`
   - Wrap every script-author-emitted echo line with the prefix (NOT the lines coming from gradlew / phpunit / temporal CLI — those have their own format). Specifically: `captured WORKFLOW_ID=…`, `wrote $OUT_FILE…`, error messages.
   - This also reconciles the README's documented behavior with reality
   - **Logging:** the change IS the logging fix

4. **#20 Tighten HistoryParityTest with explicit non-empty guard + coverage attributes**
   - File: `tests/Parity/Nexus/HistoryParity/HistoryParityTest.php`
   - Before `self::assertEquals(...)` add `self::assertNotEmpty($java, '...'); self::assertNotEmpty($php, '...');`
   - Add `#[CoversClass(NormalizerRegistry::class)]` and `#[CoversClass(EventHistoryNormalizer::class)]` to the class
   - Add `#[UsesClass(HistoryLoader::class)]`, `#[UsesClass(EventHistory::class)]`, `#[UsesClass(Source::class)]`, `#[UsesClass(PhpSdkNormalizer::class)]`, `#[UsesClass(JavaSdkNormalizer::class)]` (and the field normalizers it transitively touches — `TimestampNormalizer`, `DurationNormalizer`, `WorkflowIdNormalizer`, `IdentityNormalizer`, `TaskQueueNormalizer`, `BuildIdNormalizer`, `SdkMetadataNormalizer`, `StackTraceNormalizer`, `AbstractSdkNormalizer`)
   - **Logging:** none (test code)

5. **#21 Clarify PhpFailingCallerTest docblock about its role**
   - File: `tests/Parity/Nexus/HistoryParity/php/PhpFailingCallerTest.php`
   - Edit the existing class docblock to add a one-line explicit statement: "This test's role is to PRODUCE a captured Temporal history for cross-SDK parity comparison; deep wire-protocol assertions live in HistoryParityTest under the Parity tier — do not duplicate them here."
   - No assertion changes
   - **Logging:** none

6. **#22 Re-run end-to-end to confirm no regression**
   - `cd tests/Parity && make assert` — must still produce the same diff (cross-SDK divergences in `historySizeBytes`, `workerVersion.buildId`, `failure.source`, `applicationFailureInfo.type`)
   - `bash -n tests/Parity/Nexus/HistoryParity/scripts/run-java.sh` and same for run-php.sh — syntax check the prefix edits
   - `make -n -C tests/Parity/Nexus/HistoryParity all` — sanity check Makefile
   - **Logging:** capture command output; no new logging

## Dependencies

- #17, #18, #19, #20, #21 are all independent — fan out to subagents in parallel
- #22 blocks on all of the above

## Risks

- **#19 prefix change is cosmetic but touches `echo`-based parsing**: the `setup.sh` `if ! lsof…` doesn't parse echos, the run-java/php scripts use captured `WORKFLOW_ID=` lines parsed by `awk` and `grep`. Make sure the prefix is added ONLY to status messages, NEVER to the `WORKFLOW_ID=…` line that downstream parses. The original `run-java.sh` extracts via `grep -E '^WORKFLOW_ID='` — keep that line bare.
- **#20 `#[UsesClass]` list is long**: phpunit will WARN if a class is listed in `UsesClass` but not actually touched, but that's a non-fatal info message. Acceptable.

## Commit Plan

3 tasks per checkpoint, single commit at the end (since this is a small, cohesive cleanup):

- **Single commit (after #17–#22):** `refactor(tests/parity): trim essay comments, harden scripts, document test role`
  - Most files are gitignored; the only tracked-code change in this plan is potentially zero (everything is under `tests/Parity/`). The commit will surface as the plan file change in `.ai-factory/plans/`. No tracked source-code modifications expected from this plan.

## Next Steps

After review, run `/aif-implement` to execute. The five edit tasks (#17–#21) are independent and can be batched into one parallel-subagent fan-out, then #22 verifies.

Plan file: `.ai-factory/plans/parity-audit-cleanup.md`
