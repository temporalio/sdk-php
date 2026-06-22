# Temporal PHP SDK

## Overview
Official PHP client library (`temporal/sdk`) for the Temporal workflow orchestration platform. Enables building resilient, distributed workflows and activities in PHP. The SDK communicates with the Temporal server through RoadRunner (Go-based PHP process manager with a Temporal plugin), using a local goridge pipe for workflow execution and gRPC for client-side API calls.

## Core Features
- Workflow orchestration with PHP generators (coroutine-based execution)
- Activity definitions and invocation with retry policies
- Signal, Query, and Update handlers for workflow interaction
- Nexus RPC handler-side library for cross-namespace service calls
- Interceptor pipeline for cross-cutting concerns
- Plugin system (Worker, Client, Schedule, Connection)
- Data conversion chain (Null/Binary/ProtoJson/Proto/Json)
- Schedule and cron workflow support
- Search attribute management
- Testing framework with activity mocking and workflow replay

## Tech Stack
- **Language:** PHP 8.1+
- **Transport:** gRPC (`grpc/grpc`) + Protobuf (`google/protobuf`)
- **Worker runtime:** RoadRunner (`spiral/roadrunner`)
- **Attribute reflection:** `spiral/attributes`
- **Testing:** PHPUnit 10.5
- **Static analysis:** Psalm (level 2, strict)
- **Code style:** php-cs-fixer via `spiral/code-style`

## Architecture Notes
- Workflow methods are PHP generators; `Scope::next()` drives execution
- No event loop (ReactPHP) — RoadRunner provides the execution loop
- Replay is transparent: PHP re-executes workflows from scratch, RoadRunner resolves commands from history
- Nexus subsystem provides handler-side service routing, middleware, and serialization
- `Internal/` namespace contains non-public implementation details
- Client-side API (WorkflowClient, ScheduleClient) uses gRPC directly

## Architecture
See `.ai-factory/ARCHITECTURE.md` for detailed architecture guidelines.
**Pattern:** Layered Library with Internal/Public Boundary

## Non-Functional Requirements
- Strict types enforced everywhere (`declare(strict_types=1)`)
- Full words for identifiers, no informal abbreviations
- PHP 8 attributes (not annotations) for workflow/activity/nexus declarations
- Interface-based design with `*Interface` suffix convention
