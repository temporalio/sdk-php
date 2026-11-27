# Архитектура data-conversion подсистемы

Слои подсистемы и контракты — то, что должно быть в голове прежде чем смотреть на любой код, который сериализует или десериализует payload.

## Слои

```
       wire bytes (Payload protobuf)
                ↓
    PayloadConverterInterface[]      ← Null, Binary, ProtoJson, Proto, Json
                ↓
    DataConverterInterface           ← chain (encoding → converter)
                ↓
    EncodedValues / EncodedCollection / RawValue
                ↓
    PHP value (scalar / DTO / proto Message / Bytes / RawValue / EncodedCollection)
```

## Контракты

| Интерфейс | Файл | Зачем |
|---|---|---|
| `DataConverterInterface` | [src/DataConverter/DataConverterInterface.php](../../src/DataConverter/DataConverterInterface.php) | `fromPayload(Payload, type)` / `toPayload(value)` — высокоуровневая обёртка над цепочкой |
| `PayloadConverterInterface` | [src/DataConverter/PayloadConverterInterface.php](../../src/DataConverter/PayloadConverterInterface.php) | Низкоуровневый converter одного encoding'а: `getEncodingType()`, `toPayload()`, `fromPayload()` |
| `ValuesInterface` | [src/DataConverter/ValuesInterface.php](../../src/DataConverter/ValuesInterface.php) | **Канон** для типизации параметров/возврата — `getValue(int, type)`, `count()`, `toPayloads()` |

Базовый класс [Converter](../../src/DataConverter/Converter.php) выставляет `metadata.encoding` на исходящий `Payload`.

## Цепочка по умолчанию

[`DataConverter::createDefault()`](../../src/DataConverter/DataConverter.php:34) собирает массив, проиндексированный по encoding-string ([EncodingKeys](../../src/DataConverter/EncodingKeys.php)):

| order | converter | encoding | matches when... |
|---|---|---|---|
| 1 | [NullConverter](../../src/DataConverter/NullConverter.php) | `binary/null` | `$value === null` |
| 2 | [BinaryConverter](../../src/DataConverter/BinaryConverter.php) | `binary/plain` | `$value instanceof Bytes` |
| 3 | [ProtoJsonConverter](../../src/DataConverter/ProtoJsonConverter.php) | `json/protobuf` | `$value instanceof Google\Protobuf\Internal\Message` |
| 4 | [ProtoConverter](../../src/DataConverter/ProtoConverter.php) | `binary/protobuf` | `$value instanceof Google\Protobuf\Internal\Message` |
| 5 | [JsonConverter](../../src/DataConverter/JsonConverter.php) | `json/plain` | всё остальное |

### Как работает диспатч

- **toPayload** ([DataConverter.php:79](../../src/DataConverter/DataConverter.php:79)) пробегает converter'ы по порядку, берёт первый, кто вернул не-null. Каждый converter сам решает, может ли он сериализовать значение, через `instanceof`-проверку и возвращает `null`, если не может.
- **fromPayload** ([DataConverter.php:48](../../src/DataConverter/DataConverter.php:48)) сначала смотрит `metadata.encoding` payload'а и матчит на конкретный converter по encoding-ключу. Плюс короткие фастпасы для `void/null/true/false` через target-type.

> **Тонкость с порядком.** ProtoJson стоит **раньше** Proto — по умолчанию любой `Google\Protobuf\Internal\Message` уйдёт в `json/protobuf`, а не в `binary/protobuf`. Если нужен binary — собирай `DataConverter` руками с переставленным порядком.

> **`EncodingKeys::METADATA_ENCODING_RAW_VALUE = 'binary'`** ([EncodingKeys.php:20](../../src/DataConverter/EncodingKeys.php:20)) — задекларирован, но никем не используется. Не путать с `binary/plain` (BinaryConverter) и `binary/protobuf` (ProtoConverter).

## RawValue: bypass через все слои

[`RawValue`](../../src/DataConverter/RawValue.php) — это публичный escape hatch:

- `toPayload` отдаёт inner payload как есть ([DataConverter.php:81](../../src/DataConverter/DataConverter.php:81)).
- `fromPayload` вернёт `RawValue`, если target-type запрошен `RawValue::class` ([DataConverter.php:52](../../src/DataConverter/DataConverter.php:52)).

Acceptance-тест [tests/Acceptance/Extra/DataConverter/RawValueTest.php](../../tests/Acceptance/Extra/DataConverter/RawValueTest.php) подтверждает контракт: workflow создаёт `RawValue(new Payload(['data' => 'hello world']))`, прокидывает через activity и получает обратно тот же payload. Это легальный канал передачи "нерасшифрованного" payload через workflow/activity-границу.

## Bytes: typed wrapper для `binary/plain`

[`Bytes`](../../src/DataConverter/Bytes.php) — отдельный typed wrapper для бинарных payload'ов. Не `string`-as-bytes — именно `Bytes` instance. `BinaryConverter::fromPayload` требует `$type === Bytes::class` и рефьюзит остальное ([BinaryConverter.php:41-46](../../src/DataConverter/BinaryConverter.php:41)).

## Type-сообщения

[`Type`](../../src/DataConverter/Type.php) — DTO, описывающий целевой тип. Конструируется из любой формы: `string | ReflectionClass | ReflectionType | ReturnType | self`. Свойства: `name`, `allowsNull`, `isArrayOf`.

```php
Type::create($x);              // нормализатор любой входной формы
Type::arrayOf(SomeDto::class); // коллекция DTO
$type->isClass();              // class_exists($name)
$type->allowsNull();           // для void/null путей
```

`@psalm-type TType = string|\ReflectionClass|\ReflectionType|Type|null` — это форма, которая везде ходит как параметр `getValue`/`fromPayload`.

## EncodedValues vs EncodedCollection

| | [EncodedValues](../../src/DataConverter/EncodedValues.php) | [EncodedCollection](../../src/DataConverter/EncodedCollection.php) |
|---|---|---|
| Ключи | целые (positional) | произвольные `array-key` (assoc) |
| Используется для | args/return workflow/activity/query/signal/update/Nexus, heartbeat details, failure details | Header / Memo / SearchAttributes |
| State | либо `payloads` (Traversable&ArrayAccess&Countable), либо `values` (raw PHP) | оба сосуществуют — `values` overrides `payloads` per-key |
| Ленивость | `getValue(i, $type)` декодирует только нужный | `getValue(name, type)` тоже ленивый |
| `setDataConverter` | поздняя инъекция (см. [интеграцию](integration.md#late-injection)) | то же |

Обе сделаны как `private __construct` + статические фабрики:

```php
EncodedValues::empty();
EncodedValues::fromValues([$arg1, $arg2]);
EncodedValues::fromPayloads($payloads, $converter);
EncodedValues::fromPayloadCollection($iter, $converter);
```

У `EncodedValues` ещё:

- **`sliceValues(converter, values, offset, len)`** ([EncodedValues.php:63](../../src/DataConverter/EncodedValues.php:63)) — split payloads. Используется в `InvokeActivity` для отщепления `heartbeatDetails` от args, и в `StartWorkflow` для отщепления `lastCompletionResult`.
- **`decodePromise(promise, type)`** ([EncodedValues.php:82](../../src/DataConverter/EncodedValues.php:82)) — оборачивает promise так, чтобы при разрешении `ValuesInterface::getValue(0, type)` декодировал результат. Используется во всех stub'ах: `ActivityStub`, `ChildWorkflowStub`, `NexusOperationStub`, `SideEffect`.

Обе знают void-семантику: `getValue(0, $voidType)` на пустых payloads вернёт `null` ([EncodedValues.php:185](../../src/DataConverter/EncodedValues.php:185)). `isVoidType` принимает `null`, строки `void/null/mixed`, `Type::allowsNull()`, `ReturnType::nullable`, `ReflectionNamedType/UnionType::allowsNull()`.

## Header как EncodedCollection

[`Temporal\Interceptor\Header`](../../src/Interceptor/Header.php) — это `final class Header extends EncodedCollection implements HeaderInterface`. Метод `toHeader(): ProtoHeader` собирает proto-Header из `toPayloadArray()`. Это и есть тот самый "header conversion path" — и в обратную сторону EncodedCollection умеет специализироваться на `Header/Memo/SearchAttributes/Payloads` через [EncodedCollectionType](../../src/Internal/Marshaller/Type/EncodedCollectionType.php) (см. [marshaller.md](marshaller.md#encodedcollectiontype)).

## См. также

- [Marshaller](marshaller.md) — как `JsonConverter` ходит в DTO через рефлексию.
- [Интеграция](integration.md) — где converter реально вызывается на путях workflow/activity/Nexus/client.
