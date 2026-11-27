# Runtime PHP-воркера

Документы про то, как PHP SDK исполняет workflows — от facade `Workflow::timer()` до gRPC-вызова `RespondWorkflowTaskCompleted` к Temporal-серверу.

## Содержание

| Документ | О чём |
|---|---|
| [Архитектура](architecture.md) | Три процесса (Temporal — RR — PHP), зачем нужен RR, где gRPC-клиент в PHP всё-таки используется |
| [Wire-протокол PHP↔RR](worker-rr-protocol.md) | Shared queue, codec (proto/json), TickInfo, request ID, главный цикл `dispatch()` |
| [Workflow и корутины](workflow-coroutines.md) | `Scope::next()` как движок генератора, event-loop без ReactPHP, replay-семантика |
| [Fiber mode](fibers.md) | `@experimental` режим без `yield`/`\Generator`: `\Fiber`-мост поверх Generator-протокола, public API map, gotchas |

## Связанное

- [Nexus subsystem](../nexus/) — отдельная подсистема со своим RR-контрактом.
- [Plugins](../plugins.md) — расширение SDK.
- [Interceptors](../interceptors/) — middleware для client/worker calls.
