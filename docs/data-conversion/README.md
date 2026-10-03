# Data conversion и payloads

Через [src/DataConverter/](../../src/DataConverter/) проходят **все** аргументы и результаты workflow'ов, activity'ей, query/signal/update, Nexus operations, а также `details` в failure'ах и `Memo`/`SearchAttributes` в start-options. Понимание этой подсистемы нужно прежде чем разговор про любую сериализацию вообще.

## Содержание

| Документ | О чём |
|---|---|
| [Архитектура](architecture.md) | Слои, цепочка `PayloadConverter`'ов по умолчанию, `EncodedValues` vs `EncodedCollection`, `Type`, `RawValue`, `Bytes` |
| [Marshaller](marshaller.md) | Как `JsonConverter` сериализует DTO: `Marshaller` + `AttributeMapper` + регистр типов, `#[Marshal]`, `EncodedCollectionType`, обратный путь `ProtoToArrayConverter` |
| [Интеграция](integration.md) | Где `DataConverter` создаётся и инжектится, поздняя инъекция через `setDataConverter`, codec wire-boundary, таблица routes, Nexus payload, failures |
| [Serialization context](serialization-context.md) | `SerializationContext` для converter'ов: подпись/шифрование payload по `workflowId`; где проставляется; расхождения с Java/Go (memo, SA, codec) |

## Quick reference

```php
// Дефолтная цепочка (порядок имеет значение).
$converter = DataConverter::createDefault();
//   1. NullConverter        binary/null
//   2. BinaryConverter      binary/plain     (Bytes)
//   3. ProtoJsonConverter   json/protobuf    (proto Message — приоритет!)
//   4. ProtoConverter       binary/protobuf  (proto Message)
//   5. JsonConverter        json/plain       (всё остальное → Marshaller)

// Один payload.
$payload = $converter->toPayload($value);
$value   = $converter->fromPayload($payload, MyDto::class);

// Список payload'ов (args/return).
$values = EncodedValues::fromValues([$arg1, $arg2]);     // workflow-side: без converter
$values = EncodedValues::fromPayloads($payloads, $conv); // wire-side: с converter
$arg    = $values->getValue(0, MyDto::class);            // ленивая декодировка

// Assoc-коллекция (Header / Memo / SearchAttributes).
$header = Header::fromValues(['x-tenant' => 'acme']);
$memo   = EncodedCollection::fromPayloadCollection($proto->getFields(), $conv);

// Bypass: payload идёт через все слои нетронутым.
$raw = new RawValue(new Payload(['data' => '...']));
```

## Главные принципы

- **`ValuesInterface` — канон.** Тип параметров/возврата для аргументов и результатов — `ValuesInterface`. `EncodedValues` используется только как factory в момент конструирования (зафиксировано в `feedback_values_interface_canon`).
- **Поздняя инъекция converter'а.** `EncodedValues::fromValues($args)` без converter'а — допустимая полу-инициализация. Converter подставляется ниже по стеку (codec encoder, `WorkflowStarter`, `FailureConverter`) перед `toPayloads()`. См. [интеграцию](integration.md#late-injection).
- **`RawValue` — публичный escape hatch.** Не internal. `RawValue` идёт unmodified через любые слои включая wire — нужно когда payload должен пройти насквозь без декодирования.
- **Marshaller — отдельная подсистема.** `JsonConverter` использует её для DTO ↔ JSON; те же самые `Marshaller` и `MarshallerInterface` дополнительно работают с `options` от RR (workflow/activity options приходят уже-десериализованным JSON-array, не payload-каналом). См. [marshaller.md](marshaller.md).

## Связанное

- [Wire-протокол PHP↔RR](../runtime/worker-rr-protocol.md) — где decoder вызывает `EncodedValues::fromPayloads()` и encoder сериализует обратно.
- [Nexus handler-side SDK](../nexus/handler-side-sdk.md) — handler принимает один payload, обёрнутый в `ValuesInterface` для единообразия.
- [Plugins](../plugins.md) — `WorkerFactoryPluginContext`/`ClientPluginContext` позволяют плагину подменить converter.
