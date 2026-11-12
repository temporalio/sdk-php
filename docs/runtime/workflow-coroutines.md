# Workflow-coroutines, event-loop и replay

Как PHP-генератор внутри workflow-метода приостанавливается на `yield` и просыпается, и почему replay не требует специальной обработки в PHP.

> **Experimental Fiber mode:** `\Temporal\Experiments\Fibers\Workflow` позволяет писать те же workflow-методы без `yield` и `\Generator`. Под капотом каждый handler оборачивается в `\Fiber`, который мостится в Generator-протокол ниже — см. [fibers.md](fibers.md). Этот документ остаётся базой: Fiber mode — это надстройка над описанным здесь циклом.

## Workflow — это PHP-generator

Любой workflow-метод — это generator:

```php
#[WorkflowMethod]
public function run(): \Generator
{
    yield Workflow::timer(5);
    yield Workflow::timer(5);
    return 'done';
}
```

`Workflow::timer()` возвращает `PromiseInterface`. Когда `yield` отдаёт promise, **сам PHP** не знает что с ним делать — он просто приостанавливает генератор. Возобновить его можно только извне через `Generator::send($value)`.

**Кто-то** должен:
1. Получить yield'нутое значение (`$gen->current()`).
2. Если это promise — навесить on-fulfilled, который позже сделает `$gen->send($result)`.
3. Если promise отрезолвился — продолжить генератор.
4. Повторить пока `$gen->valid()` не станет `false`.

Этим занимается `Scope`.

## `Scope` — обёртка над генератором

[src/Internal/Workflow/Process/Scope.php:41](../../src/Internal/Workflow/Process/Scope.php:41).

Каждый workflow-coroutine выполняется внутри `Scope`. Главный workflow-метод — корневой scope; каждый дочерний `Workflow::async()`, `signal handler`, `update handler` — отдельный дочерний scope. Scope'ы образуют дерево cancellation.

Scope хранит:
- `DeferredGenerator $coroutine` — обёртка над PHP-генератором с lazy-start семантикой ([src/Internal/Workflow/Process/DeferredGenerator.php](../../src/Internal/Workflow/Process/DeferredGenerator.php));
- `Deferred $deferred` — promise, который резолвится результатом всего scope'а;
- `string $layer` — какой event-layer использовать для defer'а возобновлений;
- `bool $cancelled`, `bool $detached` — состояние scope'а.

## Движок: `Scope::next()`

[src/Internal/Workflow/Process/Scope.php:412](../../src/Internal/Workflow/Process/Scope.php:412):

```php
protected function next(): void
{
    $this->makeCurrent();
    begin:
    $this->context->resolveConditions();

    if (!$this->coroutine->valid()) {
        $this->onResult($this->coroutine->getReturn());
        return;
    }

    $current = $this->coroutine->current();

    switch (true) {
        case $current instanceof Workflow\Mutex:
            $this->nextPromise($this->context->await($current));
            break;
        case $current instanceof PromiseInterface:
            $this->nextPromise($current);
            break;
        case $current instanceof Deferred:
            $this->nextPromise($current->promise());
            break;
        case $current instanceof RequestInterface:
            // yield Request — допустимая короткая запись для yield client->request($r)
            $this->nextPromise($this->context->getClient()->request($current, $this->scopeContext));
            break;
        case $current instanceof \Generator:
            $this->nextPromise($this->createScope(false)->attach($current));
            break;
        default:
            // не-promise значение — пропускаем дальше без ожидания
            $this->coroutine->send($current);
            goto begin;
    }
}
```

**Решение `next()` зависит от типа yield'утого значения.** Promise / Deferred / Mutex — навешиваем callback и suspend'имся. Generator — заворачиваем в дочерний Scope. RequestInterface — короткая запись для «отправь и подожди». Любое другое значение — просто пропускаем.

## Возобновление: `Scope::nextPromise()`

[src/Internal/Workflow/Process/Scope.php:463](../../src/Internal/Workflow/Process/Scope.php:463):

```php
private function nextPromise(PromiseInterface $promise): void
{
    $onFulfilled = function (mixed $result): mixed {
        $this->defer(function () use ($result): void {
            $this->makeCurrent();
            $this->coroutine->send($result);   // ← возобновление генератора
            $this->next();                     // ← смотрим следующий yield
        });
        return $result;
    };
    $onRejected = function (\Throwable $e): void {
        $this->defer(fn() => $this->handleError($e));
    };
    $promise->then($onFulfilled, $onRejected);
}
```

Ключевой момент: **возобновление откладывается через `defer()`**, не выполняется синхронно в callback'е. Это нужно чтобы все callback'и из одного «батча» резолвов отработали в правильном порядке (по layer'ам).

## Event-loop без ReactPHP

В SDK **нет** ReactPHP-loop'а. Вместо него — простой event-emitter с фиксированным порядком слоёв.

[src/WorkerFactory.php:86](../../src/WorkerFactory.php:86):

```php
class WorkerFactory implements WorkerFactoryInterface, LoopInterface
```

`WorkerFactory` сам реализует `LoopInterface` через `EventEmitterTrait` ([src/Internal/Events/EventEmitterTrait.php](../../src/Internal/Events/EventEmitterTrait.php)).

`tick()` — это просто `emit()` всех слоёв по порядку:

```php
public function tick(): void
{
    $this->emit(LoopInterface::ON_SIGNAL);
    $this->emit(LoopInterface::ON_CALLBACK);
    $this->emit(LoopInterface::ON_QUERY);
    $this->emit(LoopInterface::ON_TICK);
    $this->emit(LoopInterface::ON_FINALLY);
}
```

`Scope::defer()` ([src/Internal/Workflow/Process/Scope.php:550](../../src/Internal/Workflow/Process/Scope.php:550)):

```php
private function defer(\Closure $tick): void
{
    $this->services->loop->once($this->layer, $tick);
    $this->services->queue->count() === 0 and $this->services->loop->tick();
}
```

`once()` — добавить one-shot listener. Вторая строка — **если очередь outbound-команд пустая, тикнуть прямо сейчас**. Это позволяет цепочке резолвов «провалиться насквозь» в одном dispatch'е, не дожидаясь следующего batch'а от RR.

## Полный путь одного `yield Workflow::timer(5)`

```
                        ┌─ Workflow::timer(5)
                        │   = NewTimer #9001 push в очередь
                        │   возвращает Deferred[9001]->promise()
                        │
yield ──▶ Scope::next() ──▶ current() = PromiseInterface
                        │
                        ├─ nextPromise($promise)
                        │   $promise->then(onFulfilled, onRejected)
                        │
                        └─ выход из next() — coroutine suspended

                        ┌────── batch уходит в RR ──────┐
                        │       (включая NewTimer #9001) │
                        └─────────────────────────────────┘

                        ┌────── через какое-то время:    ┐
                        │   RR пушит SuccessResponse #9001 │
                        └─────────────────────────────────┘

dispatch() ──▶ Client::dispatch(#9001) ──▶ Deferred[9001]->resolve($payloads)
                                              │
                                              └─▶ react-promise → onFulfilled
                                                   └─▶ defer(fn) → loop->once(ON_TICK, fn)

dispatch() ──▶ tick() ──▶ emit(ON_TICK) ──▶ fn():
                                            coroutine->send($result)  ← generator resume
                                            next()                    ← следующий yield
                                                 └─▶ если новый yield — снова push, suspend
                                                 └─▶ если return — onResult($return)
```

## Replay: PHP не знает и не должен знать

При падении воркера и переисполнении workflow'а **PHP не делает ничего особенного**. Он:
1. Запускает workflow с самого начала.
2. Снова создаёт `NewTimer #9001`, push'ит в очередь.
3. Снова suspend'ится на promise.
4. Снова отдаёт batch RR'у.

Разница только в **скорости ответа RR**. При replay'е RR (точнее Temporal Go SDK внутри `rrtemporal`) видит, что в history уже есть `TimerStartedEvent #9001` и `TimerFiredEvent #9001`, и **не идёт на сервер**, а сразу формирует `SuccessResponse #9001` в следующем batch'е к PHP.

Для PHP это выглядит как «promise зарезолвился очень быстро». Никакого специального cache на стороне PHP нет.

### Что в PHP знает про replay

[src/Worker/Environment/Environment.php:36](../../src/Worker/Environment/Environment.php:36) хранит `bool $isReplaying`, обновляемый из `TickInfo` каждого пришедшего сообщения.

Использования:

| Место | Что делает |
|---|---|
| [src/Internal/Workflow/Logger.php:82](../../src/Internal/Workflow/Logger.php:82) | Глушит логи во время replay'а (`enableLoggingInReplay` по умолчанию `false`) |
| [src/Internal/Workflow/Process/Process.php:163](../../src/Internal/Workflow/Process/Process.php:163), [:239](../../src/Internal/Workflow/Process/Process.php:239) | Передаёт в `WorkflowInfo` (read-only API для пользователя) |
| [src/Internal/Workflow/Process/Process.php:300](../../src/Internal/Workflow/Process/Process.php:300) | Подавляет handler-state warning'и |
| [src/Internal/Workflow/WorkflowContext.php:266](../../src/Internal/Workflow/WorkflowContext.php:266) | Не шлёт `setCurrentDetails` повторно при replay |
| [src/Internal/Transport/Router/InvokeUpdate.php:60](../../src/Internal/Transport/Router/InvokeUpdate.php:60) | Не запускает validation handler у update'а при replay |

И **всё**. `Client::request()` ([src/Internal/Transport/Client.php:81](../../src/Internal/Transport/Client.php:81)) безусловно делает `queue->push($request)` — флаг replay не проверяется.

### Почему так правильно

- **PHP-stateless относительно истории.** Память между tick'ами не должна сохраняться, иначе при падении воркера replay не сработает.
- **Один кодовый путь.** В workflow-коде не нужно `if (Workflow::isReplaying())`. Алгоритм одинаков.
- **Детерминизм проверяется автоматически.** Если код пошёл другим путём — counter `Request::$lastID` назначит другой ID, RR увидит mismatch с history, упадёт с nondeterminism error.

## Тонкости

- **`Request::$lastID` сбрасывается на 9000 при старте процесса воркера** — потому что это `static` поле класса. RR при replay поднимает свежий PHP-процесс, ID-нумерация повторяется bit-for-bit.
- **`Scope` объединяет coroutine и cancellation scope** в один объект (комментарий [Scope.php:35](../../src/Internal/Workflow/Process/Scope.php:35) явно отмечает это отличие от Java SDK, где они разделены).
- **`DeferredGenerator` подменяет завершённый generator на dummy-генератор** ([DeferredGenerator.php:176](../../src/Internal/Workflow/Process/DeferredGenerator.php:176)) — чтобы `Iterator::next()`-вызовы после завершения не падали.
- **`yield SomeRequest`** — короткая запись, обрабатываемая в `Scope::next()` через `RequestInterface` ветку. Эквивалентно `yield $client->request($req)`.
- **`yield $value` где `$value` — не promise** — `next()` сразу делает `send($value)` и переходит к `goto begin;`. То есть generator движется без suspension'а.
- **`Scope::defer()` тикает loop сразу** если outbound-очередь пустая — позволяет цепочкам резолвов работать в одном dispatch'е.

## См. также

- [Fiber-mode runtime](fibers.md) — опциональный режим без `yield`, тот же event-loop.
- [Wire-протокол PHP↔RR](worker-rr-protocol.md) — как именно `NewTimer` уходит в RR.
- [Архитектура runtime'а](architecture.md) — общая картина.
