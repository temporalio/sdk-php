# Интеграция: где converter реально вызывается

Карта мест, где `DataConverter` / `EncodedValues` / `EncodedCollection` создаются и инжектятся в системе. Без этого разговор про сериализацию повисает в воздухе — converter сам по себе ничего не делает, важен путь от его конструирования до реального `toPayload`/`fromPayload`-вызова.

## Где DataConverter создаётся

| Место | Как |
|---|---|
| [WorkerFactory ctor](../../src/WorkerFactory.php:120) | required ctor arg + `WorkerFactoryPluginContext.dataConverter` для override плагинами |
| [WorkerFactory::create](../../src/WorkerFactory.php:153) | static factory, default `DataConverter::createDefault()` |
| [WorkflowClient ctor](../../src/Client/WorkflowClient.php:81) | optional ctor arg + `ClientPluginContext` override; default → createDefault |
| [ScheduleClient ctor](../../src/Client/ScheduleClient.php:58) | то же что у WorkflowClient |
| [ScheduleHandle](../../src/Client/Schedule/ScheduleHandle.php:38), [UpdateHandle](../../src/Client/Update/UpdateHandle.php:32) | required ctor arg, инжектится из родительского клиента |
| [JsonCodec / ProtoCodec](../../src/WorkerFactory.php:367) | конструируются в `WorkerFactory` из `$this->converter` после плагинного override |
| [NexusTaskHandler](../../src/Internal/Nexus/NexusTaskHandler.php:58) | required ctor arg |
| [ActivityInvocationCache](../../src/Worker/ActivityInvocationCache/RoadRunnerActivityInvocationCache.php:30) | optional, default → createDefault (для testing) |

`WorkerFactory` дополнительно создаёт [`Marshaller`](../../src/WorkerFactory.php:350) (`new Marshaller(new AttributeMapperFactory($reader))`) и кладёт его в `ServiceContainer`, откуда его берут все routes.

## Wire-граница: codec ↔ router

Decoder ([JsonCodec/Decoder.php](../../src/Worker/Transport/Codec/JsonCodec/Decoder.php)) принимает массив-команду от RR и:

1. base64-декодит `payloads` → `Payloads` proto.
2. base64-декодит `header` → `Header` proto.
3. Заворачивает через `EncodedValues::fromPayloads($payloads, $dataConverter)` и `Header::fromPayloadCollection($headers->getFields(), $dataConverter)` ([Decoder.php:64-66](../../src/Worker/Transport/Codec/JsonCodec/Decoder.php:64)).
4. Конструирует `ServerRequest` (или `SuccessResponse` / `FailureResponse`).

Encoder ([JsonCodec/Encoder.php](../../src/Worker/Transport/Codec/JsonCodec/Encoder.php)) — обратный путь. Тонкость: **converter инжектится в payloads через `setDataConverter()`** прямо перед сериализацией ([Encoder.php:37, 41, 71](../../src/Worker/Transport/Codec/JsonCodec/Encoder.php:37)).

`ProtoCodec` параллелен; codec выбирается через `$_SERVER['RR_CODEC']` в [WorkerFactory.php:367](../../src/WorkerFactory.php:367).

## <a id="late-injection"></a>Поздняя инъекция converter'а

`EncodedValues::fromValues($args)` без converter'а — это допустимая полу-инициализация: state-машина "values without converter". Converter добавляется позже, когда payloads реально нужны:

| Где инжектится | Файл |
|---|---|
| Workflow → wire (codec encoder) | [JsonCodec/Encoder.php:37, 71](../../src/Worker/Transport/Codec/JsonCodec/Encoder.php:37) |
| Client → server (start request) | [WorkflowStarter.php:177, 409](../../src/Internal/Client/WorkflowStarter.php:177) |
| Update args (start update) | [WorkflowStub.php:291](../../src/Internal/Client/WorkflowStub.php:291) |
| Schedule memo / SA | [ScheduleClient.php:116-117](../../src/Client/ScheduleClient.php:116) |
| Schedule action | [ScheduleMapper.php:35-38](../../src/Internal/Mapper/ScheduleMapper.php:35) |
| Failure details | [TemporalFailure.php:80](../../src/Exception/Failure/TemporalFailure.php:80) (override'ы в `ApplicationFailure`/`CanceledFailure`/`TimeoutFailure`) |

`getValue()` на values-без-converter не падает, **если индекс уже есть в `$values`-массиве** ([EncodedValues.php:125](../../src/DataConverter/EncodedValues.php:125)) — converter нужен только когда лезут в `payloads[]`.

## Routes (worker-side)

Каждый route десериализует входной `payloads` через `EncodedValues` (декодеру оно уже передано codec'ом) и сериализует результат через `EncodedValues::fromValues()`.

| Route | Вход (`payloads`) | Выход (`resolver->resolve`) |
|---|---|---|
| [StartWorkflow](../../src/Internal/Transport/Router/StartWorkflow.php:47) | wf args + (опц.) `lastCompletionResult` — `sliceValues` | `EncodedValues::fromValues([null])` (start ack) |
| [InvokeActivity](../../src/Internal/Transport/Router/InvokeActivity.php:52) | activity args + (опц.) `heartbeatDetails` — `sliceValues` | `EncodedValues::fromValues([$result])` |
| [InvokeQuery](../../src/Internal/Transport/Router/InvokeQuery.php:91) | query args | `EncodedValues::fromValues([$result])` |
| [InvokeSignal](../../src/Internal/Transport/Router/InvokeSignal.php) | signal args | `EncodedValues::fromValues([null])` |
| [InvokeUpdate](../../src/Internal/Transport/Router/InvokeUpdate.php:53) | update args через `UpdateInput` | `EncodedValues::fromValues([$value])` через `UpdateResponse` |
| [CancelNexusOperation](../../src/Internal/Transport/Router/CancelNexusOperation.php) | token | `EncodedValues::fromValues([null])` |

Workflow input создаётся в [Input](../../src/Internal/Workflow/Input.php) — DTO с тремя полями (`info: WorkflowInfo`, `input: ValuesInterface`, `header: Header`). Marshaller'ом распаковывается из `options['info']`, а `input`/`header` присваиваются из request'а руками ([StartWorkflow.php:65-70](../../src/Internal/Transport/Router/StartWorkflow.php:65)).

`StartWorkflow` отдельно конвертирует `Memo` и `SearchAttributes` через `EncodedCollection::fromPayloadCollection` ([StartWorkflow.php:122](../../src/Internal/Transport/Router/StartWorkflow.php:122)) — payload-коллекция из proto разворачивается в `EncodedCollection` с лениво-декодируемыми value'ами.

## Workflow stubs (caller-side)

| Stub | Файл | Что делает |
|---|---|---|
| `ActivityStub` | [ActivityStub.php:65](../../src/Internal/Workflow/ActivityStub.php:65) | `EncodedValues::fromValues($args)` → `ExecuteActivity` request, `decodePromise()` на response |
| `ChildWorkflowStub` | [ChildWorkflowStub.php](../../src/Internal/Workflow/ChildWorkflowStub.php) | Аналогично через `ExecuteChildWorkflow` |
| `NexusOperationStub` | [NexusOperationStub.php:73](../../src/Internal/Workflow/NexusOperationStub.php:73) | `EncodedValues::fromValues($args)` → `ExecuteNexusOperation` request |
| `ExternalWorkflowStub` | [ExternalWorkflowStub.php](../../src/Internal/Workflow/ExternalWorkflowStub.php) | Сигналы внешнему workflow |
| `WorkflowContext::sideEffect()` | [WorkflowContext.php:285](../../src/Internal/Workflow/WorkflowContext.php:285) | Оборачивает значение в `EncodedValues::fromValues([$value])` для записи в history |

Все они создают `EncodedValues` без converter'а — он подставляется ниже, в codec encoder'е.

## Nexus

Nexus payload — **один**, не список. Всё равно заворачивается в `ValuesInterface` для единообразия с остальной частью SDK.

Caller-side (workflow → RR):
- [`NexusOperationStub::start()`](../../src/Internal/Workflow/NexusOperationStub.php:73) пакует `args: EncodedValues::fromValues($args)` в `ExecuteNexusOperation`.

Handler-side (RR → handler):
- [`NexusTaskHandler::buildInputFromPayload()`](../../src/Internal/Nexus/NexusTaskHandler.php:310) принимает один `Payload` от RR → `Payloads{[$payload]}` → `EncodedValues::fromPayloads(...)`.
- [`ServiceHandler::startOperation()`](../../src/Nexus/Handler/Internal/ServiceHandler.php:103) декодирует input через `$input->getValue(0, $definition->inputType)` где `$definition->inputType` извлечён из `#[Operation]/#[AsyncOperation]` атрибута. На ошибке десериализации — `HandlerException(BadRequest)`.
- [`ServiceHandler::encodeResult()`](../../src/Nexus/Handler/Internal/ServiceHandler.php:231) — **прямой** `dataConverter->toPayload($result)` (не через `EncodedValues::fromValues()`), потом `Payloads{[$payload]}` обратно в `EncodedValues`. На ошибке — `HandlerException(Internal)`.
- [`NexusTaskHandler::startOperationDirect()`](../../src/Internal/Nexus/NexusTaskHandler.php:223) — путь без proto `Response`-обёртки (для прямого RR-вызова): для async ставит `payload.data = token`, `payload.metadata` `[KIND_KEY => KIND_ASYNC, LINKS_KEY => json]`. Это тот wire-shape, который зафиксирован в `project_nexus_caller_wire_design`.

## Failures

`details`/`heartbeatDetails` в failure'ах — это `ValuesInterface`, и для них действует то же правило поздней инъекции converter'а:

- [`TemporalFailure::setDataConverter`](../../src/Exception/Failure/TemporalFailure.php:80) — пустой no-op в базе.
- Override'ится в [ApplicationFailure](../../src/Exception/Failure/ApplicationFailure.php:99), [CanceledFailure](../../src/Exception/Failure/CanceledFailure.php:33), [TimeoutFailure](../../src/Exception/Failure/TimeoutFailure.php:48) — пробрасывает converter в `details`/`lastHeartbeatDetails`.
- [`FailureConverter::mapExceptionToFailure`](../../src/Exception/Failure/FailureConverter.php:55) сначала вызывает `e->setDataConverter`, потом дёргает `details->toPayloads()`.

Получение failure'а на client-side — [`FailureConverter::mapFailureToException`](../../src/Exception/Failure/FailureConverter.php:43) — обратный путь: разворачивает proto Failure в SDK-исключение и кладёт converter внутрь, чтобы `getDetails()` мог декодировать на ленивом доступе.

## Client-side: декодирование response'ов

[`ResponseToResultMapper::mapUpdateWorkflowResponse`](../../src/Internal/Client/ResponseToResultMapper.php:55) — пример client-side декодирования: payload'ы из `UpdateWorkflowExecutionResponse` оборачиваются в `EncodedValues::fromPayloads($success, $this->converter)`. Дальше caller вызовет `getValue(0, $type)` с нужным типом.

Аналогично `WorkflowStub::getResult()` декодирует workflow result через `EncodedValues::fromPayloads()`.

## Plugin override

Плагины могут подменить converter в трёх контекстах:

| Контекст | Метод |
|---|---|
| [`WorkerFactoryPluginContext`](../../src/Plugin/WorkerFactoryPluginContext.php:32) | `setDataConverter(?DataConverterInterface)` — применяется в [WorkerFactory ctor](../../src/WorkerFactory.php:148) до создания codec'а |
| [`ClientPluginContext`](../../src/Plugin/ClientPluginContext.php:49) | то же для `WorkflowClient` |
| [`ScheduleClientPluginContext`](../../src/Plugin/ScheduleClientPluginContext.php:45) | то же для `ScheduleClient` |

Это единственный поддерживаемый способ глобально подменить converter — например, навесить шифрование payload'ов через chain decorator.

## См. также

- [Архитектура](architecture.md) — converter chain и контракты.
- [Marshaller](marshaller.md) — DTO ↔ array через рефлексию.
- [Wire-протокол PHP↔RR](../runtime/worker-rr-protocol.md) — что codec делает с payload'ом.
- [Plugins](../plugins.md) — как подменить converter глобально.
