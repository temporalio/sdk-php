# Интеграция Nexus с RoadRunner

Что должен делать RR, чтобы Nexus заработал в Temporal-окружении PHP-воркера. Описывает обе стороны: handler-side (RR доставляет Nexus-задание PHP) и caller-side (workflow вызывает Nexus-операцию).

## Двунаправленный контракт

```
Handler-side: RR ──▶ PHP — RR доставляет входящие Nexus-задания
              PHP ──▶ RR — PHP возвращает start-result или cancel-confirm

Caller-side:  PHP ──▶ RR — workflow декларирует Nexus-вызов
              RR ──▶ PHP — RR возвращает started-сигнал и completion
```

## RR → PHP (handler-side)

Воркер регистрирует Nexus-сервисы; RR доставляет ему задания и собирает ответы.

### Маршрут `InvokeNexusOperation`

[src/Internal/Transport/Router/InvokeNexusOperation.php](../../src/Internal/Transport/Router/InvokeNexusOperation.php).

**Что RR должен прислать в `options`:**

| Ключ | Тип | Что это |
|---|---|---|
| `service` | string | Nexus service name |
| `operation` | string | Nexus operation name |
| `requestId` | string | spec'овый Start-RequestId. Если пусто — PHP сгенерит fallback (для совместимости с не-Nexus HTTP-клиентами) |
| `callback` | string\|null | URL для async-completion callback |
| `callbackHeaders` | map<string,string> | Headers с уже стрипнутым префиксом `Nexus-Callback-` |
| `headers` | map<string,string> | Request headers (включая `Operation-Timeout` / `Request-Timeout` — из них вычисляется deadline) |
| `links` | array | `Nexus-Link` из start-запроса (uri+type) |
| `invocationId` | uint64 | RR-internal ID для cooperative interrupt; 0 = back-compat fallback |

Плюс `payload` с input'ом операции в формате Temporal `Payload`.

**Что воркер возвращает RR — типизированная reply-команда `NexusOperationStarted`:**

Route — это thin adapter над каноническим
[`NexusTaskHandler::handleStartOperation(Request)`](../../src/Internal/Nexus/NexusTaskHandler.php).
Он строит `Temporal\Api\Nexus\V1\Request` из JSON `options` + `payloads`, зовёт
канонический хендлер, и переводит `Response.StartOperation.Variant` в типизированный
reply [`NexusOperationStarted`](../../src/Worker/Transport/Command/Client/NexusOperationStarted.php).
Encoder сериализует его как `Message.command = "NexusOperationStarted"` + JSON `options` +
опциональный `payloads`.

| Исход | `Message.command` | `options` JSON | `payloads` | `failure` |
|---|---|---|---|---|
| Sync success | `NexusOperationStarted` | `{async: false, links?}` | `[result_payload]` | — |
| Async success | `NexusOperationStarted` | `{async: true, token, links?}` | — | — |
| `OperationException` (Failed/Canceled) | — | — | — | `failurepb.Failure` с `ApplicationFailureInfo.Type = "nexus.OperationError.{failed\|canceled}"` (через `FailureConverter::mapExceptionToFailure`, ветка `NexusOperationException`); recursive `cause` сохраняется |
| `HandlerException` / generic `\Throwable` | — | — | — | `failurepb.Failure` с `NexusHandlerFailureInfo` (через `FailureConverter::mapExceptionToFailure`, ветка `NexusHandlerException`) |

Никаких payload-metadata маркеров (`_rr_nexus_kind`, `_rr_nexus_links` и пр.) — control-plane (sync/async, links) живёт в `options`, payload data — это чистый результат. Go-сторона декодирует `Message.command = "NexusOperationStarted"` в `internal.NexusOperationStarted{Async, Token, Links}` DTO.

#### Контракт preservation cause-chain (handler → caller)

`roadrunner-temporal/aggregatedpool/nexus.go::nexusErrorFromFailure` оборачивает PHP-присланный `failurepb.Failure` через `temporal.GetDefaultFailureConverter().FailureToError(f)`. Полученный go-error реализует `failureHolder` и держит исходный proto verbatim — поэтому SDK-Go `ErrorToFailure(...)` short-circuit'ит к нему обратно (`sdk-go/internal/failure_converter.go:71-75`), и **рекурсивная cause-цепочка с типами и `ApplicationFailureInfo.Details` (Payloads) восстанавливается на server-side без потерь**. Аналог тому, что уже делал activity-путь в `aggregatedpool/activity.go::Activity.execute` (`temporal.GetDefaultFailureConverter().FailureToError(retPld.Failure)`).

Для `OperationException` плагин также выставляет `Message: f.GetMessage()` на `*nexus.OperationError` — иначе SDK-Go упаковывал бы внешний wrap с пустым message'ом (`sdk-go/internal/internal_nexus_task_handler.go:259`).

Caller-side цепочка (подтверждено дампом workflow history):

```
NexusOperationFailure                                               ← server-injected wrapper
 └── ApplicationFailure(type='nexus.OperationError.{failed|canceled}', message, …)  ← оригинал из PHP
      └── … (рекурсивный cause из user-кода, если есть)
```

SDK-Go в `internal_nexus_task_handler.go:258-265` строит промежуточный `ApplicationError(type='OperationError', …)` wrap при упаковке ответа, но **сервер срезает этот уровень** при формировании `NexusOperationFailedEvent.failure.cause` — caller workflow видит оригинальный PHP-`ApplicationFailure` напрямую как `$e->getPrevious()`. Каноничный паттерн `AppFailureCallerWorkflow::run` в `tests/Acceptance/Extra/Nexus/SyncFailure/SyncFailureTest.php`.

### Маршрут `CancelNexusOperation`

[src/Internal/Transport/Router/CancelNexusOperation.php](../../src/Internal/Transport/Router/CancelNexusOperation.php).

**Options:** `{service, operation, operationToken}`. Response: пустой Payload.

Это spec'овый cancel-by-token. RR получил `POST /{service}/{operation}/cancel?token=...` от внешнего caller'а и пробросил его в PHP. PHP резолвит cancel-рутину операции — автоматически для `WorkflowHandle`-операций (SDK декодирует токен и отменяет backing-workflow) либо через `cancel()` ручного `OperationHandlerInterface`.

**Sync-операции не отменяемы**: `ServiceHandler` бросит `HandlerException(NotImplemented)` если операция помечена `#[Operation]`, а не `#[AsyncOperation]`. Сообщение: `'Operation %s/%s is synchronous and cannot be cancelled'`.

### Маршрут `CancelNexusOperationMethod`

[src/Internal/Transport/Router/CancelNexusOperationMethod.php](../../src/Internal/Transport/Router/CancelNexusOperationMethod.php).

**Options:** `{invocationId: uint64, reason: string}`. Late cancel (handler уже завершился) = no-op.

Это **не Nexus-cancel**. Это RR-уровневый «прерви этот PHP-вызов» — для deadline'ов / shutdown'ов / транспортных отмен. Воркер устанавливает флаг в `OperationContext::isMethodCancelled()`. Handler должен поллить или регистрировать listener.

**Зачем две разные cancel-точки:**
- Nexus-cancel идёт по `(service, operation, token)` и работает на уровне operation state machine. Идемпотентен.
- Method-cancel идёт по RR-internal `invocationId` и работает на уровне PHP-call cooperative interrupt'а. Без него нельзя реализовать deadline'ы или сложно остановить worker во время handler'а.

### Расширение `GetWorkerInfo`

[src/Internal/Transport/Router/GetWorkerInfo.php:80](../../src/Internal/Transport/Router/GetWorkerInfo.php:80) — воркер сверх обычной анкеты отдаёт массив:

```php
'NexusServices' => [
    ['name' => 'my.service.v1', 'operations' => ['greet', 'longJob', ...]],
    ...
]
```

Без этого RR не знает, какие service'ы и операции воркер реально хостит, и не сможет зарегистрировать их в Temporal-сервере во время handshake'а.

## PHP → RR (caller-side)

Workflow внутри своего метода может позвать Nexus-операцию — и тогда контракт работает в обратную сторону.

### Запрос `ExecuteNexusOperation`

[src/Internal/Transport/Request/ExecuteNexusOperation.php](../../src/Internal/Transport/Request/ExecuteNexusOperation.php).

**Поля:** `endpoint, service, operation, options, nexusHeaders` + args (input).

> ⚠️ **Тонкость**: пустой `nexusHeaders` форсится в `\stdClass()` — не PHP-array. Иначе JSON-encode даст `[]`, а Go-сторона декодит как `map[string]string` и упадёт.

RR не делает gRPC к Nexus endpoint'у напрямую. RR трансферит этот вызов в Temporal Go SDK API (`workflow.NewNexusClient(endpoint, service).ExecuteOperation(...)`), а тот формирует команду `ScheduleNexusOperationCommand` в текущем `WorkflowTaskCompletedRequest`. **Сетевую часть делает Temporal-сервер** через свой Nexus-gateway.

### Запрос `GetNexusOperationStarted`

[src/Internal/Transport/Request/GetNexusOperationStarted.php](../../src/Internal/Transport/Request/GetNexusOperationStarted.php).

**Поля:** `{id: <ID исходного ExecuteNexusOperation>}`.

Long-poll за start-envelope: RR держит запрос как listener, при срабатывании started-callback'а отдаёт PHP один Payload с JSON `NexusStartEnvelope = {async: bool, token?: string}`.

**Зачем отдельный listener:** Nexus-Start может вернуть токен **до** completion'а (handler ack'нул start, но операция ещё работает). Workflow может захотеть отменить async-операцию между started и completion — для этого ему нужен started-факт. Полный аналог [src/Internal/Transport/Request/GetChildWorkflowExecution.php](../../src/Internal/Transport/Request/GetChildWorkflowExecution.php) для child workflow.

### Completion — обычный future

Когда операция действительно завершилась (sync сразу или async через callback), RR резолвит то самое `ExecuteNexusOperation`-future стандартным путём — `SuccessResponse` с тем же id, что и оригинальный запрос. Никакой отдельной «verbь-команды» для completion'а нет.

## Чек-лист для RR-side имплементации

| Что RR обязан делать | Зачем | Что ломается без этого |
|---|---|---|
| Парсить `NexusServices` из `GetWorkerInfo` | Регистрация на Temporal-сервере | Сервер не знает, что воркер хостит Nexus |
| Эмитить `InvokeNexusOperation` task с полными options + payload | Spec-compliant Start | Handler не получит requestId/callback/headers/links |
| Декодить `Message.command = "NexusOperationStarted"` reply с JSON `options` `{async, token?, links?}` | Различить sync vs async и пробросить handler-emitted links | Sync vs async не различимы; links теряются |
| Эмитить `CancelNexusOperation` task по `(service, operation, token)` | Spec-овый cancel | Caller'ы не смогут отменять async-операции |
| Эмитить `CancelNexusOperationMethod` с `invocationId` | Cooperative deadline/shutdown | Handler нельзя прерывать локально, deadline'ы не работают |
| Принимать `ExecuteNexusOperation` команду от PHP в WorkflowTaskCompleted | Caller-side workflow → Nexus | Workflow не сможет звать Nexus |
| Резолвить `GetNexusOperationStarted` listener при started-callback | Workflow видит started до completion | Отмена async-операций из workflow невозможна до завершения |
| Резолвить `ExecuteNexusOperation` future при completion | Стандартный completion-path | Future never resolves |
| Эмитить ненулевой `invocationId` | Связь method-cancel'а с in-flight handler'ом | Method-cancel — no-op (back-compat ветка) |

## Тонкости и ловушки

- **`invocationId = 0` — back-compat ветка.** Pre-Nexus RR-плагины не присылали это поле. PHP трактует 0 как «cooperative cancel недоступен», просто не регистрирует canceller. Современные RR-builds всегда присылают ненулевой `invocationId`.
- **Cancel sync-операции — `NotImplemented`.** Это не баг, это spec-совместимое поведение. Sync операция уже завершилась к моменту, когда handler ответил.
- **Никаких payload-metadata маркеров.** Прежняя схема со sync/async и links через `_rr_nexus_*` ключи в payload metadata удалена. Control-plane живёт в типизированном `NexusOperationStarted` reply (`options` JSON), payload data — чистый результат.
- **`callbackHeaders` уже без префикса `Nexus-Callback-`.** Стрипает RR (per spec), не PHP.
- **`OperationStartDetails::$requestId` обязателен и непустой.** Если RR не прислал его — PHP сгенерит fallback (`bin2hex(random_bytes(8))`). В нормальном Nexus-трафике он всегда есть; fallback — для странных HTTP-клиентов.
- **Deadline вычисляется из header'ов, не из RR-options.** `OperationTimeout` / `RequestTimeout` парсятся в [NexusTaskHandler::deadlineFromHeaders()](../../src/Internal/Nexus/NexusTaskHandler.php) — case-insensitive, malformed → `null`.
- **`nexusHeaders` ≠ `header`** в `ExecuteNexusOperation`. Первое — raw-string Nexus headers, для wire'а; второе — Temporal interceptor-header с типизированными payload-значениями. Их нельзя смешивать.
- **Один canonical handler-путь.** `NexusTaskHandler` экспонирует только `handleStartOperation(Request): Response` и `handleCancelOperation(Request): Response` — те же сигнатуры, что у sibling SDK (sdk-go, sdk-java, sdk-typescript). Прежний `*Direct` bridge удалён.

## См. также

- [Nexus spec](spec.md) — что именно RR должен соблюдать на wire-уровне.
- [PHP handler-side SDK](handler-side-sdk.md) — как user-метод связан с маршрутами.
- [Wire-протокол PHP↔RR](../runtime/worker-rr-protocol.md) — общий транспорт.
