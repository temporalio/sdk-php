# Wire-протокол PHP ↔ RoadRunner

Всё, что происходит между моментом «PHP создал команду» и моментом «RR получил её batch'ем». Описывает только PHP-сторону протокола; что делает Go-плагин внутри RR — отдельная тема.

## Двунаправленный канал на одной очереди

PHP-воркер держит единственную in-memory очередь `ArrayQueue` ([src/Internal/Queue/ArrayQueue.php](../../src/Internal/Queue/ArrayQueue.php)), создаваемую в [src/WorkerFactory.php:331](../../src/WorkerFactory.php:331). В неё пушатся **и outbound requests** (наши команды к RR), **и inbound responses** (наши ответы на server-requests от RR):

```
                 ┌─────────────────────────────┐
PHP outbound ──▶ │                             │ ──▶ encoder ──▶ RR
                 │      shared ArrayQueue      │
PHP inbound  ──▶ │                             │
responses        └─────────────────────────────┘
```

**Owner'ы:**
- [src/Internal/Transport/Client.php](../../src/Internal/Transport/Client.php) — пушит в очередь outbound `RequestInterface` (например `NewTimer`), хранит таблицу `requests[id] => Deferred`.
- [src/Internal/Transport/Server.php](../../src/Internal/Transport/Server.php) — пушит в ту же очередь ответы (`SuccessClientResponse`/`FailedClientResponse`) на server-requests, которые пришли от RR.

В конце каждого `dispatch()`-вызова всё содержимое очереди **одним батчем** улетает в RR.

## Главный цикл `dispatch()`

[src/WorkerFactory.php:381](../../src/WorkerFactory.php:381):

```php
private function dispatch(string $messages, array $headers): string
{
    $commands = $this->codec->decode($messages, $headers);

    foreach ($commands as $command) {
        $this->env->update($command->getTickInfo());
        if ($command instanceof ServerResponseInterface) {
            $this->client->dispatch($command);     // ответ RR на наш прошлый request
            continue;
        }
        $this->server->dispatch($command, $headers); // новый server-request от RR
    }

    $this->tick();                                  // прокручиваем event-loop

    return $this->codec->encode($this->responses); // сливаем очередь в proto-frame
}
```

Шаги:
1. Декодируем batch от RR в коллекцию `CommandInterface`.
2. Для каждой команды — либо `Client::dispatch` (резолв нашего deferred'а), либо `Server::dispatch` (запуск нашего обработчика).
3. `tick()` — прокручиваем все отложенные callback'и (см. [workflow-coroutines.md](workflow-coroutines.md)). Здесь же выполняются workflow-coroutine'ы и наполняют очередь новыми outbound-командами.
4. Кодируем всю очередь в proto-frame и возвращаем.

`waitBatch()`/`send()` оборачивают goridge-receive и goridge-send в [src/Worker/Transport/RoadRunner.php](../../src/Worker/Transport/RoadRunner.php).

## Кодек: `proto` или `json`

Выбирается через env var `RR_CODEC` ([src/WorkerFactory.php:367-376](../../src/WorkerFactory.php:367)). Дефолт — JSON, но в production обычно `proto`.

Proto-формат: [src/Worker/Transport/Codec/ProtoCodec/Encoder.php:35](../../src/Worker/Transport/Codec/ProtoCodec/Encoder.php:35) — каждая команда становится `RoadRunner\Temporal\DTO\V1\Message`:

```protobuf
message Message {
    int64 id = 1;
    string command = 2;     // имя команды, например "NewTimer"
    string options = 3;     // JSON-строка с опциями
    Payloads payloads = 4;  // Temporal Payloads
    Header header = 5;      // interceptor-header
    Failure failure = 6;
    // + tick-метаданные:
    string tick_time = 8;
    int64 history_length = 9;
    int64 history_size = 10;
    bool replay = 11;
    bool continue_as_new_suggested = 12;
}

message Frame {
    repeated Message messages = 1;
}
```

> Тонкость: пустой `options` сериализуется как `'{}'` через `\stdClass`, не `'[]'`. Go-сторона ждёт map, не array — пустой PHP-массив сломает декод. См. [src/Worker/Transport/Codec/ProtoCodec/Encoder.php:49-51](../../src/Worker/Transport/Codec/ProtoCodec/Encoder.php:49) и [src/Internal/Transport/Request/ExecuteNexusOperation.php:53](../../src/Internal/Transport/Request/ExecuteNexusOperation.php:53) — там это сделано явно для `nexusHeaders`.

## Request ID — process-local автоинкремент

[src/Worker/Transport/Command/Client/Request.php:30](../../src/Worker/Transport/Command/Client/Request.php:30):

```php
protected static int $lastID = 9000;
// ...
private function getNextID(): int
{
    $next = ++static::$lastID;
    if ($next >= \PHP_INT_MAX) {
        $next = static::$lastID = 1;
    }
    return $next;
}
```

**Ньюансы:**
- ID — `static`, общий на все request'ы внутри **одного PHP-процесса**, не per-workflow.
- Старт с 9000, чтобы не конфликтовать с RR-internal ID-ами в тех редких случаях, когда они пересекаются.
- Wrap при достижении `PHP_INT_MAX` — формально безопасный, но на практике недостижимый.
- ID — это **тэг для матчинга request↔response в PHP-side таблице**, не идентификатор для Temporal-сервера. У сервера свой `event_id`/`command_id` в history.

## Три ID-пространства

При работе с Nexus легко запутаться — поэтому фиксируем явно:

| ID | Где живёт | Назначение |
|---|---|---|
| `Request::$id` (9000+) | PHP, process-local | Связь PHP-side request ↔ его deferred |
| `command_id` / `event_id` | Temporal history | Workflow-scoped позиция в истории |
| `invocationId` | RR-internal | Идентификация in-flight handler-вызова на стороне RR (для cooperative cancel) |

Эти три счётчика **не совпадают** и не должны сравниваться напрямую.

## TickInfo: метаданные каждого WorkflowTask

[src/Worker/Transport/Command/Server/TickInfo.php](../../src/Worker/Transport/Command/Server/TickInfo.php):

```php
final class TickInfo
{
    public function __construct(
        public readonly \DateTimeInterface $time,
        public readonly int $historyLength = 0,
        public readonly int $historySize = 0,
        public readonly bool $continueAsNewSuggested = false,
        public readonly bool $isReplaying = false,
    ) {}
}
```

Декодер кладёт `TickInfo` в каждое декодированное сообщение ([src/Worker/Transport/Codec/ProtoCodec.php:65-71](../../src/Worker/Transport/Codec/ProtoCodec.php:65)). В `dispatch()` затем `env->update($command->getTickInfo())` записывает поля в [src/Worker/Environment/Environment.php:36](../../src/Worker/Environment/Environment.php:36) — именно отсюда читается `Workflow::isReplaying()`, `Workflow::now()`.

## См. также

- [Архитектура runtime'а](architecture.md) — общий контекст.
- [Workflow и корутины](workflow-coroutines.md) — как `tick()` запускает coroutine'ы и почему replay не требует специальной логики в PHP.
