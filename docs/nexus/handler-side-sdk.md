# Nexus handler-side SDK в PHP

Как [спека Nexus](spec.md) выглядит в коде. Описывает как user-facing API под `Temporal\Nexus\`, так и внутренний дispatch под `Temporal\Internal\Nexus\`.

## Декларация сервиса

Service — это **класс с `#[Service]`** и методами, помеченными `#[Operation]` (sync) или `#[AsyncOperation]` (async):

```php
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\WorkflowHandle;

#[Service]
final class MyService
{
    #[Operation]
    public function greet(string $name): string
    {
        return "hello, {$name}";
    }

    #[AsyncOperation(output: 'string')]
    public function longJob(string $input): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(MyWorkflow::class, $options, $input);
    }
}
```

Async-метод возвращает `WorkflowHandle` — SDK сам стартует backing-workflow и минтит
operation token. Отмена автоматическая: SDK декодирует токен и отменяет backing-workflow
(отдельный `#[OperationCancel]`-метод не нужен — атрибут удалён). Для полного ручного
контроля метод может вернуть `OperationHandlerInterface` — тогда он сам несёт `start` + `cancel`.

> Это текущая модель (после удаления `#[ServiceImpl]` / `#[OperationImpl]` /
> `#[OperationCancel]` / `SynchronousOperationHandler`). Старые атрибуты и классы удалены.

## Static-accessor `Nexus::`

Внутри метода-обработчика контекст dispatch'а доступен через статический фасад [src/Nexus/Nexus.php](../../src/Nexus/Nexus.php):

| Метод | Что возвращает |
|---|---|
| `Nexus::getCurrentContext()` | `NexusContext` — активное состояние dispatch'а |
| `Nexus::getCurrentOperationContext()` | `OperationContext` (handler-side: links, headers, deadline) |
| `Nexus::getStartDetails()` | `OperationStartDetails` (requestId, callback, links) — только для start-метода |
| `Nexus::getCancelDetails()` | `OperationCancelDetails` (operationToken) — только для cancel-метода |
| `Nexus::getOperationContext()` | `NexusOperationContext` (Temporal-side: namespace, taskQueue, workflowClient) |

`getOperationContext()` пробрасывает `WorkflowClient`, который воркер отдаёт в
`NexusTaskHandler` (см. [src/WorkerFactory.php](../../src/WorkerFactory.php)) — handler
через него может стартовать workflow, signal'ить и т.д.

## `OperationStartResult` — sealed union

[src/Nexus/Handler/OperationStartResult.php](../../src/Nexus/Handler/OperationStartResult.php):

```php
abstract readonly class OperationStartResult
{
    protected function __construct() {}

    public static function sync(mixed $value = null): SyncOperationStartResult { ... }
    public static function async(OperationInfo $info): AsyncOperationStartResult { ... }
}
```

Конструкторы наследников помечены `@internal` — конструировать только через фабрики `::sync()` / `::async()`. Это калька с Go-шной `HandlerStartOperationResult[Sync|Async]` пары, но PHP-идиоматично через abstract readonly + final readonly.

Для метода с `#[AsyncOperation]` тип возврата — `WorkflowHandle` (а не `OperationStartResult`); SDK сам стартует backing-workflow через `WorkflowRunStarter` и обернёт результат в `AsyncOperationStartResult`.

## `OperationContext` — почти immutable

[src/Nexus/Handler/OperationContext.php](../../src/Nexus/Handler/OperationContext.php). Содержит `service`, `operation`, нормализованные headers (lowercased keys), `deadline?`, `serviceDefinition?`, `LinkCollection $links`, `MethodCanceller?`, `ClockInterface`.

**Тонкости:**

- `LinkCollection $links` — **shared mutable**. Это сделано специально: middleware могут добавлять links, и они должны оставаться видимыми после `withServiceDefinition()`-копирования контекста. Все остальные поля immutable.
- Если `deadline` задан, но `methodCanceller` не передан — он создаётся автоматически из deadline'а. Когда deadline истечёт, `MethodCanceller` сработает и зарегистрированные listener'ы получат сигнал.
- `isMethodCancelled()` — это RR-уровневая отмена (deadline, shutdown, остановка handler'а воркером), **не** Nexus-spec'овая отмена операции (вторая идёт через `cancelOperation` — автоматически для `WorkflowHandle`-операций либо через `cancel()` ручного `OperationHandlerInterface`).
- `headers` нормализуются к lowercase keys в конструкторе.

## `OperationStartDetails` / `OperationCancelDetails`

[src/Nexus/Handler/OperationStartDetails.php](../../src/Nexus/Handler/OperationStartDetails.php):

```php
final readonly class OperationStartDetails
{
    public function __construct(
        public string $requestId,                // обязательный, непустой
        public ?string $callbackUrl = null,
        public array $callbackHeaders = [],      // префикс Nexus-Callback- уже стрипнут транспортом
        public array $links = [],                // Link[]
    ) { /* validation */ }
}
```

[src/Nexus/Handler/OperationCancelDetails.php](../../src/Nexus/Handler/OperationCancelDetails.php):

```php
final readonly class OperationCancelDetails
{
    public function __construct(public string $operationToken) {
        OperationTokenValidator::assert($operationToken);
    }
}
```

Оба VO валидируют поля в конструкторе через [src/Nexus/Validation/](../../src/Nexus/Validation/) — `OperationTokenValidator`, `ServiceNameValidator`, `OperationNameValidator`, `PrintableAsciiValidator`. Все они кидают `Temporal\Nexus\Exception\InvalidArgumentException`.

## Внутренний dispatch: `ServiceHandler`

[src/Nexus/Handler/Internal/ServiceHandler.php](../../src/Nexus/Handler/Internal/ServiceHandler.php) — приватная middle-tier между транспортом и user-методом. Делает:

1. Резолв `(service, operation) → NexusServiceInstance + OperationHandlerInterface` через таблицу инстансов. Если service неизвестен — `HandlerException(NotFound)` (`'Unrecognized service ...'`). Если операция неизвестна — `HandlerException(NotFound)`.
2. Применяет middleware-цепочку (внешний middleware применяется к итоговому handler'у первым).
3. Десериализует input через DataConverter; ошибка → `HandlerException(BadRequest)`.
4. Зовёт user-метод.
5. Для sync-результата сериализует output через DataConverter; ошибка → `HandlerException(Internal)`.
6. **Sync-операции не отменяемы**: если `cancelOperation` пришёл на операцию, у которой `$definition->async === false`, бросает `HandlerException(NotImplemented)` с сообщением `'Operation %s/%s is synchronous and cannot be cancelled'`.

## Failure converter

[src/Nexus/Internal/Failure/NexusFailureConverter.php](../../src/Nexus/Internal/Failure/NexusFailureConverter.php) — единственное место, которое конвертирует PHP-исключения (`OperationException`, `HandlerException`) в proto-Failure объект для wire'а:

- `operationExceptionToProto()` — для `424 Failed Dependency` (terminal failed/canceled);
- `handlerExceptionToProto()` — для `4xx/5xx` (handler error c retry-семантикой).

Транспортная glue (`NexusTaskHandler`) использует только эти два метода — JSON-формат failure'а нигде больше не собирается вручную.

## Temporal-side контекст и `WorkflowClient`

Temporal-side поля (`namespace`, `taskQueue`, `workflowClient`) приходят в
`NexusTaskHandler` от воркера и складываются в [src/Internal/Nexus/NexusContext.php](../../src/Internal/Nexus/NexusContext.php);
`Nexus::getOperationContext()` отдаёт их как `NexusOperationContext`. `WorkflowClient`
создаётся в [src/WorkerFactory.php](../../src/WorkerFactory.php) и передаётся в
`NexusTaskHandler` (см. [src/Nexus/Nexus.php](../../src/Nexus/Nexus.php) — `getWorkflowClient()`).
Через него handler-методы запускают workflow'ы и делают cross-workflow операции. Без
`WorkflowClient` `Nexus::getWorkflowClient()` бросит `\LogicException` — handler сможет
работать только в рамках чистой Nexus-семантики (sync-операции без обращения к workflow API).

## `MethodCanceller`

[src/Nexus/Handler/MethodCanceller.php](../../src/Nexus/Handler/MethodCanceller.php). RR-уровневый cooperative interrupt:
- может быть создан с `deadline` — тогда сам сработает по истечении;
- срабатывает извне через `cancel($reason)`;
- listener'ы (`MethodCancellationListenerInterface`) получают callback при срабатывании;
- если listener регистрируется **после** срабатывания, он вызывается синхронно тут же (см. [OperationContext.php:84](../../src/Nexus/Handler/OperationContext.php:84)).

Handler должен **поллить** `OperationContext::isMethodCancelled()` или регистрировать listener'ы. PHP не умеет асинхронно прерывать чужой stack frame — отмена кооперативная.

## Тонкости

- **Cancel sync-операции** — `NotImplemented`. Это отражает фундаментальное свойство sync-операций: они уже завершились к моменту, когда handler отвечает; отменять нечего.
- **`callbackHeaders` приходят в handler без префикса `Nexus-Callback-`** — стрипает транспорт, не handler. Это спецификационное поведение.
- **Разделение `OperationContext` / `NexusOperationContext`** — первый чисто handler-side (Nexus-spec поля), второй Temporal-side (namespace + taskQueue + workflow API). Не смешивать.
- **`Link::$type` / `Link::$uri`** — обязательные поля; парсер `LinkParser` строгий, малформированные ссылки → `HandlerException(BadRequest)`, никаких silent-drop'ов.
- **Validators throwing'ом контролируют граница** — service/operation names валидируются на каждом конструкторе, не где-то «в конце».
- **`ServiceHandler` принимает middleware** в конструкторе как массив `OperationMiddlewareInterface[]`. Применяются reverse-order (последний в массиве — внутренний).

## См. также

- [Nexus spec](spec.md) — каноническая HTTP-спека.
- [RR-интеграция](rr-integration.md) — что приходит от RR в `NexusTaskHandler` и обратно.
- [Архитектура runtime'а](../runtime/architecture.md) — общий контекст PHP↔RR.
