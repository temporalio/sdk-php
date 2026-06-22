# Architecture: Layered Library with Internal/Public Boundary

## Overview
The Temporal PHP SDK follows a layered library architecture with a strict public/internal boundary. The public API (`Temporal\Workflow`, `Temporal\Activity`, `Temporal\Client`, etc.) exposes stable interfaces and facades, while the `Internal` namespace contains implementation details that are not part of the public contract. Cross-cutting concerns (interceptors, plugins, data converters) are organized into their own top-level namespaces with well-defined extension points.

This architecture was chosen because the project is a client SDK — its primary concern is providing a clean, stable public API while keeping internal mechanics (wire protocol, coroutine management, command routing) hidden from consumers.

## Decision Rationale
- **Project type:** SDK/library (not an application)
- **Tech stack:** PHP 8.1+, gRPC, Protobuf, RoadRunner
- **Key factor:** Public API stability and clear extension points are paramount for an SDK consumed by external developers

## Folder Structure
```
src/
├── Activity/               # Public: Activity declarations, context, options
├── Client/                 # Public: WorkflowClient, ScheduleClient, ServiceClient
│   ├── GRPC/               #   gRPC connection and transport layer
│   ├── Schedule/           #   Schedule client
│   ├── Workflow/           #   Workflow client
│   └── Update/             #   Update client
├── Common/                 # Public: Shared DTOs (RetryOptions, SearchAttributes, etc.)
├── DataConverter/          # Public: Serialization interfaces and implementations
├── Exception/              # Public: Exception hierarchy
├── Interceptor/            # Public: Interceptor interfaces and pipeline contracts
├── Nexus/                  # Public: Nexus RPC handler-side library
│   ├── Attribute/          #   PHP 8 attributes (#[Service], #[Operation], etc.)
│   ├── Exception/          #   Nexus-specific exceptions
│   ├── Handler/            #   Service handler, operation routing
│   └── Validation/         #   Name/token validators
├── Plugin/                 # Public: Plugin system interfaces
├── Worker/                 # Public: Worker factory, RoadRunner integration
│   └── Transport/          #   Transport layer (RoadRunner communication)
├── Workflow/               # Public: Workflow attributes and definitions
└── Internal/               # Private: Implementation details (not public API)
    ├── Activity/           #   Activity invocation internals
    ├── Client/             #   Client internals
    ├── Declaration/        #   Declaration registry and reflection
    ├── Interceptor/        #   Interceptor pipeline implementation
    ├── Marshaller/         #   DTO marshalling
    ├── Nexus/              #   Nexus worker integration wiring
    ├── Transport/          #   Wire protocol (PHP ↔ RoadRunner)
    └── Workflow/           #   Workflow process, scope, coroutine management
```

## Dependency Rules

- ✅ `Internal\*` → any public namespace (implements public interfaces)
- ✅ `Client\*` → `Common\*`, `DataConverter\*`, `Exception\*`
- ✅ `Interceptor\*` → `Common\*`, `Workflow\*`, `Activity\*` (references domain types)
- ✅ `Nexus\*` → `Common\*`, `Exception\*`, `Workflow\*`
- ✅ `Worker\*` → `Internal\*` (worker bootstraps internal machinery)
- ❌ Public namespaces → `Internal\*` (consumers must never depend on internals)
- ❌ `Activity\*` → `Workflow\*` or vice versa (peer namespaces are independent)
- ❌ `DataConverter\*` → `Client\*` (lower-level must not depend on higher-level)

## Layer/Module Communication

- **User code → SDK:** Via static facades (`Workflow::*`, `Activity::*`) and client instances (`WorkflowClient`)
- **SDK → RoadRunner:** Via `Internal\Transport` — declarative commands sent over goridge pipe
- **SDK → Temporal Server:** Via `Client\GRPC\ServiceClient` — gRPC calls for client-side operations only
- **Extension points:** Interceptor interfaces for inbound/outbound workflow and activity calls; Plugin interfaces for worker/client/connection lifecycle hooks

## Key Principles

1. **Public API stability** — All public types live outside `Internal/`. Breaking changes to public interfaces require major version bumps. `Internal/` can change freely between releases.
2. **Interface-driven design** — Public contracts use `*Interface` suffixes. Concrete implementations live in `Internal/` or alongside the interface. Consumers program against interfaces.
3. **Attribute-based declarations** — Workflows, Activities, and Nexus services are declared via PHP 8 attributes (`#[WorkflowInterface]`, `#[ActivityInterface]`, `#[Service]`), not annotations or configuration files.
4. **Transparent replay** — Workflow code is deterministic; the SDK generates declarative commands. RoadRunner handles replay by resolving commands from event history. PHP code does not contain replay logic.

## Code Examples

### Interceptor extension point
```php
<?php

declare(strict_types=1);

namespace App\Interceptor;

use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;

final class LoggingInterceptor implements WorkflowInboundCallsInterceptor
{
    public function handleSignal(SignalInput $input, callable $next): void
    {
        // Cross-cutting concern before delegation
        $next($input);
    }

    public function execute(WorkflowInput $input, callable $next): mixed
    {
        return $next($input);
    }
}
```

### Public/Internal boundary
```php
<?php

// Public interface — stable contract for consumers
namespace Temporal\DataConverter;

interface DataConverterInterface
{
    public function toPayload(mixed $value): Payload;
    public function fromPayload(Payload $payload, Type $type): mixed;
}

// Internal implementation — can change between releases
namespace Temporal\Internal\DataConverter;

final class DataConverter implements DataConverterInterface
{
    // Implementation details hidden from consumers
}
```

## Anti-Patterns
- ❌ **Importing from `Internal\`** in user code — these types can change without notice
- ❌ **Bypassing facades** — using internal scope/process objects directly instead of `Workflow::*` static methods
- ❌ **Non-deterministic workflow code** — using `rand()`, `time()`, file I/O, or network calls inside workflow methods
- ❌ **Abbreviating identifiers** — use full words (`$attributes` not `$attrs`, `$options` not `$opts`)
- ❌ **Annotations instead of attributes** — always use PHP 8 `#[Attribute]` syntax
