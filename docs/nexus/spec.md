# Nexus RPC: каноническая спецификация

Краткая выжимка из [nexus-rpc/api/SPEC.md](https://github.com/nexus-rpc/api/blob/main/SPEC.md) — того, что важно держать в голове при работе с PHP-handler-side SDK и RR-интеграцией.

## Что такое Nexus

Тонкий слой поверх HTTP для system-to-system RPC. Долгие операции моделируются поверх синхронных HTTP-вызовов через дискриминированный union «sync result vs async token».

**Caller** зовёт **handler**. Handler отвечает либо результатом сразу (синхронный режим), либо токеном для продолжения (асинхронный режим). Caller не определяет режим заранее; он просто передаёт callback URL, и handler уведомит, когда завершит асинхронную операцию.

## Адресация операции

```
endpoint URL  +  service name  +  operation name  +  operation token
```

- `service` / `operation` — непустые строки, любые валидные URL-encoded символы.
- `operation token` — не пустой, только printable ASCII (валидные значения HTTP-заголовка). Его выдаёт handler в ответ на StartOperation.

## Только два эндпоинта

| Метод | Путь | Назначение |
|---|---|---|
| `POST` | `/{service}/{operation}` | StartOperation |
| `POST` | `/{service}/{operation}/cancel` | CancelOperation |

Никаких отдельных «get token», «poll status», «fetch result» в спеке нет (есть deprecated GetResult/GetInfo, но в современной модели они избыточны: результат прилетает на callback).

## StartOperation — sealed union по статусу ответа

| Status | Что значит | Body |
|---|---|---|
| `200 OK` + `Nexus-Operation-State: succeeded` | Sync success | Сериализованный результат |
| `201 Created` | Started, async | JSON `OperationInfo {token, state}` |
| `424 Failed Dependency` | Operation failed/canceled (terminal) | JSON `Failure` (`metadata.type=nexus.OperationError`, `details.state=failed\|canceled`) |
| `4xx` / `5xx` | Handler error | JSON `Failure` (`metadata.type=nexus.HandlerError`, `details.type=BAD_REQUEST\|...`) |

**Один ответ. Sync XOR async.** Не бывает «сначала токен, потом результат отдельным StartOperation-вызовом».

## CancelOperation

`POST /{service}/{operation}/cancel`. Токен передаётся либо через query-param `?token=...`, либо через заголовок `Nexus-Operation-Token`. Ответ: `202 Accepted`, body пустой.

**Cancel идемпотентен.** Повтор по уже терминальной операции должен возвращать успех. `HandlerError` бросается только на routing/permission/transport ошибки, **не** на «уже отменено» / «уже завершено».

## OperationState (wire-values, lowercase)

```
running | succeeded | failed | canceled
```

В PHP — [src/Nexus/OperationState.php](../../src/Nexus/OperationState.php). Эти строки — часть протокола; менять их = ломать совместимость.

## Failure schema (рекурсивная)

```yaml
type: object
properties:
  message: string
  stackTrace: string?
  metadata: map<string, string>?
  details: any?            # JSON-сериализуемые structured details
  cause: $ref              # вложенный Failure
```

Две предопределённые формы (различаются по `metadata.type`):

### `OperationError`
```json
{
  "metadata": {"type": "nexus.OperationError"},
  "message": "...",
  "details": {"state": "canceled" | "failed"}
}
```
Терминальный outcome операции.

### `HandlerError`
```json
{
  "metadata": {"type": "nexus.HandlerError"},
  "message": "...",
  "details": {"type": "BAD_REQUEST | ...", "retryableOverride": false}
}
```
Ошибка обработки запроса. У каждого `type` есть default retry-семантика, переопределяемая через `retryableOverride`.

## Predefined HandlerError types

| Имя | HTTP | Retry default |
|---|---|---|
| `BAD_REQUEST` | 400 | no |
| `UNAUTHENTICATED` | 401 | no |
| `UNAUTHORIZED` | 403 | no |
| `NOT_FOUND` | 404 | no |
| `REQUEST_TIMEOUT` | 408 | yes |
| `CONFLICT` | 409 | no |
| `RESOURCE_EXHAUSTED` | 429 | yes |
| `INTERNAL` | 500 | yes |
| `NOT_IMPLEMENTED` | 501 | no |
| `UNAVAILABLE` | 503 | yes |
| `UPSTREAM_TIMEOUT` | 520 | yes |

Маппинг туда-обратно — [src/Nexus/Exception/ErrorType.php](../../src/Nexus/Exception/ErrorType.php) (`httpStatus()` / `fromHttpStatus()`).

## Канонические заголовки

| Заголовок | На каком этапе | Назначение |
|---|---|---|
| `Nexus-Callback-*` | Start request | Префикс стрипается транспортом, остальное прикладывается к callback POST |
| `Nexus-Link: <url>; type="..."` | Start request, response, callback | Ассоциация с ресурсами (двусторонняя) |
| `Operation-Timeout: <num>(ms\|s\|m)` | Start request | Сколько caller готов ждать |
| `Request-Timeout` | Любой запрос | HTTP-уровневый таймаут |
| `Nexus-Operation-Token` | Cancel request, callback POST | Token-by-header alternative |
| `Nexus-Operation-State` | Sync success response, callback POST | Терминальный/промежуточный state |
| `Nexus-Operation-Start-Time` | Callback POST | RFC 5322 timestamp начала |
| `Nexus-Operation-Close-Time` | Callback POST | RFC 3339 timestamp завершения |

## Callback POST (для async-операций)

Когда async-операция завершилась, handler делает `POST <callback-url>` с:
- `Nexus-Operation-Token`, `Nexus-Operation-Start-Time`, `Nexus-Operation-Close-Time`, `Nexus-Operation-State`
- любыми `Nexus-Callback-*` headers, переданными при Start (без префикса);
- любыми `Nexus-Link` headers;
- body: либо результат + `Content-*` headers, либо JSON `Failure` с `Content-Type: application/json`.

Caller отвечает `200 OK` с пустым body на успешный приём.

## Content types

Не опинионирован. Типичные:
- `application/json` — для JSON;
- `application/octet-stream` — для byte-buffer'ов;
- пустой — для `null` payload'а;
- `application/x-protobuf; message-type=com.example.MyMessage` — binary proto;
- `application/json; format=protobuf; message-type=...` — JSON-сериализованный proto.

## Reference SDK shape (Go)

[`sdk-go/nexus/handler.go`](../../../../go/pkg/mod/github.com/nexus-rpc/sdk-go@v0.6.0/nexus/handler.go) даёт каноническую модель handler'а:

```go
type Handler interface {
    StartOperation(ctx, service, operation string, *LazyValue, StartOperationOptions)
        (HandlerStartOperationResult[any], error)
    CancelOperation(ctx, service, operation, token string, CancelOperationOptions) error
}

// Sealed union:
type HandlerStartOperationResult[T any] interface { /* sealed */ }
type HandlerStartOperationResultSync[T any] struct { Value T }
type HandlerStartOperationResultAsync struct { OperationToken string }
```

PHP-шейп [src/Nexus/Handler/](../../src/Nexus/Handler/) повторяет эту модель идиоматично через abstract-readonly-class + final-readonly-наследников; см. [handler-side-sdk.md](handler-side-sdk.md).

## Что важно держать в голове

1. Wire-значения (state-string, error-type-string, имена заголовков) — это **протокол**, не деталь реализации.
2. Start — один ответ, sync ИЛИ async. Если на стороне сервера тебе хочется «вернуть и token, и result» — это бага в спеке твоего сервиса.
3. Cancel идемпотентен. «Уже отменено» — успех, не ошибка.
4. Async-результат доставляется на callback URL. **Получение результата по токену — это уже Temporal-уровень**, не Nexus spec. На PHP-handler-side `WorkflowRunOperation` решает это поверх Nexus-async модели.
5. `OperationError` ≠ `HandlerError`. Первое — терминальное состояние операции; второе — ошибка обработки запроса с retry-семантикой.

## См. также

- [PHP handler-side SDK](handler-side-sdk.md) — как Nexus-API выглядит в PHP-коде.
- [RR-интеграция](rr-integration.md) — что нужно от RR, чтобы Nexus заработал в Temporal-окружении.
