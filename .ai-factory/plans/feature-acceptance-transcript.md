# Feature: Port acceptance-transcript mechanism to a fresh worktree from master

Created: 2026-05-14
Target branch: `feature/acceptance-transcript` (new, from `master`)
Worktree: `../sdk-php-transcript`

## Settings

- Testing: yes (3 dedicated transcript acceptance tests + 2 unit tests gate)
- Logging: standard
- Docs: yes (`docs/testing/transcript-capture.md` must port too)
- Roadmap linkage: skip

## Why

The acceptance-transcript feature (per-process structured log files capturing wire frames, PSR-3 logs, exceptions, workflow history, fatals) currently lives in the `nexus` branch but is logically independent of Nexus SDK work. Isolating it on a fresh branch from `master` makes it cleanly mergeable upstream without pulling in unrelated Nexus changes.

## Pre-flight (Phase 0) — completed

Master compatibility verified:
- ✅ `WorkflowInboundCallsInterceptor`, `ActivityInboundInterceptor`, `HostConnectionInterface`, `Plugin/CompositePipelineProvider`, `Workflow` — present on master
- ❌ `NexusOperationInboundCallsInterceptor` — **NOT** on master. Mitigation: skip `TranscriptNexusInterceptor.php` and its wiring; transcript-acceptance tests do not use Nexus.

## Files to port

### New (copy from staged working tree)
- `tests/Acceptance/App/Logger/TranscriptWriter.php`
- `tests/Acceptance/App/Logger/TranscriptReader.php`
- `tests/Acceptance/App/Logger/TranscriptSection.php`
- `tests/Acceptance/App/Logger/TranscriptLine.php`
- `tests/Acceptance/App/Logger/TranscriptAdapter.php`
- `tests/Acceptance/App/Logger/FanoutLogger.php`
- `tests/Acceptance/App/Logger/ActivityLog.php`
- `tests/Acceptance/App/Logger/MalformedTranscriptException.php`
- `tests/Acceptance/App/Transport/RecordingHost.php`
- `tests/Acceptance/App/Runtime/FatalHandler.php`
- `tests/Acceptance/App/Interceptor/TranscriptActivityInterceptor.php`
- `tests/Acceptance/App/Interceptor/TranscriptWorkflowInterceptor.php`
- `bin/transcript-merge.php`
- `docs/testing/transcript-capture.md`
- `tests/Unit/Logger/TranscriptWriterTestCase.php`
- `tests/Unit/Logger/FatalHandlerTestCase.php`
- `tests/Acceptance/Extra/Transcript/TranscriptHappyPathTest.php`
- `tests/Acceptance/Extra/Transcript/TranscriptRetryTest.php`
- `tests/Acceptance/Extra/Transcript/TranscriptWorkflowFailureTest.php`

### Skipped (depend on Nexus surfaces missing in master)
- `tests/Acceptance/App/Interceptor/TranscriptNexusInterceptor.php`

### Modified — surgical merge
- `tests/Acceptance/App/TestCase.php` — TEST_START/END/HISTORY hooks
- `tests/Acceptance/worker.php` — `FatalHandler::register`, `RecordingHost` wrap
- `tests/Acceptance/bootstrap.php` — wipe `runtime/tests/transcripts/`, init writer
- `tests/Acceptance/.rr.yaml` — env: `TEMPORAL_WIRE_TRACE`, `TEMPORAL_TRANSCRIPT_DIR`
- `tests/Acceptance/App/Feature/WorkerFactory.php` — wire workflow+activity transcript interceptors (NOT nexus)
- `tests/Acceptance/App/Logger/LoggerFactory.php` — add transcript writer factory
- `composer.json` — scripts: `test:transcript:last`, `clean:transcripts`
- `.gitignore` — add `/runtime/tests/transcripts/`

## Tasks

- [x] Phase 0 — Pre-flight: verify master has required interceptor interfaces (done)
- [x] Phase 1.1 — Create worktree `../sdk-php-transcript` from `master`, new branch `feature/acceptance-transcript`
- [x] Phase 1.2 — Copy untracked AI-context: `.ai-factory/`, `.claude/`, `CLAUDE.md`, `AGENTS.md`, `PLUGINS.md`, `.mcp.json`, `.ai-factory.json` to worktree
- [x] Phase 1.3 — `composer install` in worktree (regenerate lockfile under master)
- [x] Phase 2 — Copy 19 new files (listed above) from staged working tree into worktree (using `git show :<path>` for staged content)
- [x] Phase 3.1 — Surgical merge `tests/Acceptance/App/TestCase.php`
- [x] Phase 3.2 — Surgical merge `tests/Acceptance/worker.php`
- [x] Phase 3.3 — Surgical merge `tests/Acceptance/bootstrap.php`
- [x] Phase 3.4 — Surgical merge `tests/Acceptance/.rr.yaml`
- [x] Phase 3.5 — Surgical merge `tests/Acceptance/App/Feature/WorkerFactory.php` (skip Nexus interceptor wiring)
- [x] Phase 3.6 — Surgical merge `tests/Acceptance/App/Logger/LoggerFactory.php`
- [x] Phase 3.7 — Surgical merge `composer.json` (add scripts)
- [x] Phase 3.8 — Update `.gitignore` (add `/runtime/tests/transcripts/`)
- [x] Phase 4.1 — Run unit tests for transcript infra: `TranscriptWriter`, `FatalHandler` — must pass
- [x] Phase 4.2 — Run 3 transcript acceptance tests — must pass
- [x] Phase 4.3 — Run `composer test:transcript:last` after — non-empty merged transcript
- [x] Phase 4.4 — Smoke test: `composer test:accept-fast` (full) — non-regression check
- [x] Phase 5 — Report worktree path, branch name, pass counts. No commit, no push.

## Out of scope

- ❌ Nexus SDK code (`src/Nexus`, `src/Internal/Nexus`, Nexus tests, Nexus interceptor)
- ❌ `bin/poller-stress.php` (different feature)
- ❌ `RRStarter::markTestBoundary`, `WorkflowStarter::executeRequest UseExisting` patch — Nexus-debug work
- ❌ Any `src/` changes
- ❌ `git commit`, `git push`, merge back to master — explicit human approval required

## Verification gate

1. Unit tests for TranscriptWriter + FatalHandler — 100% pass
2. 3 transcript acceptance tests — 100% pass
3. `composer test:transcript:last` produces non-empty `runtime/tests/transcripts/_merged/transcript.log`
4. `composer test:accept-fast` doesn't regress vs master baseline

Failure budget: 3 fix iterations. If still red after that — stop and report scope mismatch.

## Rollback

If verification fails irreparably:
- Worktree stays — for inspection
- Branch `feature/acceptance-transcript` stays — uncommitted
- Cleanup command: `git worktree remove ../sdk-php-transcript && git branch -D feature/acceptance-transcript`
