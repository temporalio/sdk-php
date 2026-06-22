# AGENTS.md

> This file provides a structural map of the project for AI agents and new developers. Keep it updated when the project structure changes significantly.

## Project Overview
Official PHP SDK for the Temporal workflow orchestration platform. Enables building resilient, distributed workflows and activities in PHP 8.1+.

## Tech Stack
- **Language:** PHP 8.1+
- **Transport:** gRPC + Protobuf
- **Worker runtime:** RoadRunner (Go-based PHP process manager)
- **Testing:** PHPUnit 10.5
- **Static analysis:** Psalm (level 2)
- **Code style:** php-cs-fixer

## Project Structure
```
src/                        Main SDK source (namespace: Temporal\)
  Activity/                 Activity definitions, context, options
  Client/                   WorkflowClient, ScheduleClient, ServiceClient
    GRPC/                   gRPC connection and transport
    Schedule/               Schedule client
    Workflow/               Workflow client
    Update/                 Update client
  Common/                   Shared DTOs (RetryOptions, SearchAttributes, etc.)
  DataConverter/            Serialization/deserialization, type system
  Exception/                Exception hierarchy
  Interceptor/              Interceptor interfaces and pipelines
  Internal/                 Internal implementation (not public API)
    Nexus/                  Internal Nexus wiring (worker integration)
    Workflow/               Workflow process, scope, coroutine management
    Transport/              Wire protocol (PHP <-> RoadRunner)
  Nexus/                    Nexus RPC handler-side library
    Attribute/              #[Service], #[Operation], #[AsyncOperation], etc.
    Handler/                Service handler, operation routing
    Validation/             Name/token validators
  Plugin/                   Plugin system interfaces
  Worker/                   Worker factory, RoadRunner integration
  Workflow/                 Workflow definitions and attributes
testing/                    Testing framework (Temporal\Testing\)
  src/                      TestService, ActivityMocker, Environment
tests/
  Unit/                     Unit tests (*TestCase.php) — no external deps
  Functional/               Functional tests — needs Temporal server
  Acceptance/               E2E acceptance tests (*Test.php)
    Extra/                  SDK-specific acceptance tests
    Harness/                Cross-SDK harness tests
  Arch/                     Architecture constraint tests
  Fixtures/                 Test fixtures (sample workflows, activities, DTOs)
  Nexus/                    Nexus subsystem tests (Unit suite)
resources/scripts/          Code generation scripts
```

## Key Entry Points
| File | Purpose |
|------|---------|
| `src/include.php` | Autoload helpers, loaded via composer `files` |
| `composer.json` | Dependencies, scripts, autoload configuration |
| `psalm.xml` | Static analysis configuration |
| `.php-cs-fixer.dist.php` | Code style configuration |
| `tests/bootstrap.php` | Test bootstrap, auto-detects suite |
| `.rr.yaml` | RoadRunner configuration (when present) |

## Documentation
| Document | Path | Description |
|----------|------|-------------|
| CLAUDE.md | CLAUDE.md | AI agent instructions and project conventions |
| Runtime docs | docs/runtime/ | Architecture, wire protocol, coroutines, replay |
| Nexus docs | docs/nexus/ | Nexus spec, handler SDK, RR contract |
| Data conversion | docs/data-conversion/ | Converter chain, EncodedValues, Marshaller |
| Testing docs | docs/testing/ | Test types, infrastructure, mini-framework |

## AI Context Files
| File | Purpose |
|------|---------|
| AGENTS.md | Project structure map for AI agents |
| .ai-factory/DESCRIPTION.md | Project specification and tech stack |
| .ai-factory/ARCHITECTURE.md | Architecture pattern and design decisions |
| CLAUDE.md | AI agent instructions, conventions, and commands |

## Agent Rules
- Decompose chained shell commands into separate sequential calls
  - Wrong: `git checkout master && git pull`
  - Right: First `git checkout master`, then `git pull origin master`
- **Never start Temporal or RoadRunner manually before `composer test:*`.** Functional/acceptance bootstraps spin up the test server and the RR worker themselves. Launching `./temporal server start-dev` or `rr serve` by hand causes port conflicts and misleading errors. For server/worker failures, read `runtime/rr.log`, `runtime/rr.err.log`, `runtime/tests/logs/*.log` — do not try to reproduce by hand.
