# Nexus в PHP SDK

Документация по Nexus RPC — как канонической HTTP-спеке, так и её PHP-реализации в Temporal SDK.

## Содержание

| Документ | О чём |
|---|---|
| [Каноническая спека](spec.md) | Wire-протокол (Start/Cancel), Failure schema, OperationError vs HandlerError, headers, OperationState |
| [Handler-side SDK](handler-side-sdk.md) | `#[Service]`, `#[Operation]`, `#[AsyncOperation]`, `Nexus::` accessors, `ServiceHandler`, `WorkflowHandle` |
| [RR-интеграция](rr-integration.md) | Маршруты `InvokeNexusOperation`/`CancelNexusOperation`/`CancelNexusOperationMethod`, `_rr_nexus_*` reserved metadata, caller-side `ExecuteNexusOperation` |

## Внешние источники

- [nexus-rpc/api](https://github.com/nexus-rpc/api) — каноническая HTTP-спека.
- [nexus-rpc/sdk-go](https://github.com/nexus-rpc/sdk-go) — reference SDK (Go).

Handler-side SDK для PHP инлайнится прямо в этот репозиторий под `src/Nexus/` (`Temporal\Nexus\*`) — отдельного `nexus-rpc-sdk-php` репозитория больше нет.

## Связанное

- [Архитектура runtime'а](../runtime/architecture.md) — общий контекст PHP↔RR.
- [Wire-протокол PHP↔RR](../runtime/worker-rr-protocol.md) — транспорт, на котором лежит Nexus-pipe.
