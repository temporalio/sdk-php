# Архитектура runtime'а PHP-воркера

Высокоуровневое описание того, как PHP SDK исполняет workflows и activities, и почему между PHP-кодом и Temporal-сервером существует процесс RoadRunner.

## Три процесса, две границы

```
┌─────────────────┐         ┌─────────────────────┐         ┌──────────────────┐
│  Temporal       │ ◀─gRPC─▶│  RoadRunner         │ ◀─pipe─▶│  PHP worker      │
│  server         │  (TCP)  │  + rrtemporal (Go)  │ goridge │  (short-lived)   │
└─────────────────┘         └─────────────────────┘         └──────────────────┘
```

- **Temporal-сервер** говорит на gRPC. Держит history workflows, очереди тасков, таймеры.
- **RoadRunner** — Go-процесс с плагином `rrtemporal`. Держит **persistent gRPC** к Temporal, делает long-poll на task-queue'и (`PollWorkflowTaskQueue`, `PollActivityTaskQueue`, `PollNexusTaskQueue`). Внутри использует Temporal Go SDK.
- **PHP-воркер** — short-lived процесс, ничего сетевого не делает напрямую. Общается **только** с RR через локальный goridge-pipe (TCP/unix).

Граница между PHP и RR — это [Spiral Goridge](https://github.com/spiral/goridge). Не gRPC. Передаются proto-Message'ы (или JSON, см. `RR_CODEC` env var) внутри goridge-payload'а.

## Где gRPC-клиент в PHP всё-таки используется

В SDK **есть** полноценный gRPC-клиент: [src/Client/GRPC/ServiceClient.php](../../src/Client/GRPC/ServiceClient.php). Но он применяется только **вне workflow execution**:

| Use case | Использует ServiceClient? | Использует RR? |
|---|---|---|
| `WorkflowClient::start()`, `signal()`, `query()`, `describe()` | Да, напрямую | Нет |
| `ScheduleClient`, `OperatorClient` | Да, напрямую | Нет |
| Внутри workflow-метода (`Workflow::executeActivity()`, `Workflow::timer()`, …) | **Нет** | Да |
| Worker poll-loop (получение task'ов от Temporal) | Нет | Да, RR делает poll |
| Внутри Nexus-handler'а (`Nexus::getOperationContext()->getWorkflowClient()`) | Да, через client-side API | Нет |

**Принципиальная причина:** workflow обязан быть детерминированным. Прямой сетевой вызов из workflow-метода (`gRPC.call()`) сломает replay — на повторе значение может прийти другое. Поэтому workflow-команды декларативны: workflow только **записывает** намерение в очередь, а сетевой вызов делает RR в правильный момент.

## Что доставляется через RR

| Направление | Что |
|---|---|
| RR → PHP | `StartWorkflow`, `InvokeActivity`, `InvokeQuery`, `InvokeSignal`, `InvokeUpdate`, `InvokeNexusOperation`, `CancelNexusOperation`, `CancelNexusOperationMethod`, `DestroyWorkflow`, `GetWorkerInfo`, … |
| PHP → RR | `NewTimer`, `ExecuteActivity`, `ExecuteChildWorkflow`, `ExecuteNexusOperation`, `GetNexusOperationStarted`, `CompleteWorkflow`, `Panic`, `SideEffect`, `UpsertSearchAttributes`, … (всё в [src/Internal/Transport/Request/](../../src/Internal/Transport/Request/)) |

Все эти сообщения — это **команды** в логическом смысле. Один pingpong PHP↔RR соответствует одному WorkflowTask на Temporal-стороне: PHP получает batch событий, выполняет workflow до следующей точки suspension, возвращает batch команд.

## Главный цикл воркера

[src/WorkerFactory.php:271](../../src/WorkerFactory.php:271):

```php
public function run(?HostConnectionInterface $host = null): int
{
    $host ??= RoadRunner::create();
    // ...
    while ($msg = $host->waitBatch()) {
        $host->send($this->dispatch($msg->messages, $msg->context));
    }
    return 0;
}
```

— блокирующий receive из goridge, обработка batch'а, send обратно. Цикл блокируется в `waitBatch()` пока RR не пришлёт следующее задание.

## См. также

- [Wire-протокол PHP↔RR](worker-rr-protocol.md) — внутренности `dispatch()`, codec, queue, request-ID.
- [Workflow и корутины](workflow-coroutines.md) — как генератор обрабатывается, replay-семантика.
- [Nexus subsystem](../nexus/) — отдельная подсистема со своим RR-контрактом.
