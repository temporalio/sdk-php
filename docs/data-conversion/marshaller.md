# Marshaller

`JsonConverter` — **не** простой `json_encode`-обёртка. Для объектов он делегирует в [src/Internal/Marshaller/](../../src/Internal/Marshaller/) — самостоятельную подсистему DTO ↔ array на рефлексии и `Spiral\Attributes`.

## Когда `JsonConverter` идёт в `Marshaller`

[`JsonConverter::toPayload()`](../../src/DataConverter/JsonConverter.php:53) обрабатывает объекты так:

| Тип | Что делает |
|---|---|
| `\stdClass` | as-is, потом `json_encode` |
| `UuidInterface` | `->toString()` |
| `\DateTimeInterface` | RFC3339 |
| **остальное** | `Marshaller::marshal($value)` → array → `json_encode` |

На входе `fromPayload` ([JsonConverter.php:75](../../src/DataConverter/JsonConverter.php:75)) — обратная схема: для `class-string` target-type'а (не `string/int/array/...`) идёт `Marshaller::unmarshal($hashmap, $reflection->newInstanceWithoutConstructor())`.

`Marshaller` создаётся в конструкторе `JsonConverter` дефолтным методом [`createDefaultMarshaller()`](../../src/DataConverter/JsonConverter.php:178):

```php
new Marshaller(new AttributeMapperFactory(self::createDefaultReader()));
```

`createDefaultReader()` использует `Doctrine\Common\Annotations\Reader` если он доступен (composite `SelectiveReader([new AnnotationReader(), new AttributeReader()])`), иначе только `AttributeReader`.

## Marshaller: что он делает

[`Marshaller`](../../src/Internal/Marshaller/Marshaller.php) — рефлексия + кэш мапперов на класс.

| Метод | Что делает |
|---|---|
| `marshal(object): array` | Идёт по `mapper->getGetters()`, собирает hashmap |
| `unmarshal(array, object): object` | Идёт по `mapper->getSetters()`, заполняет существующий instance |

Mapper для класса собирается лениво и кэшируется в `$mappers[$class]`. Для `\stdClass` — fast-path без mapper'а.

## AttributeMapper: getter/setter на свойство

[`AttributeMapper`](../../src/Internal/Marshaller/Mapper/AttributeMapper.php) для каждого свойства класса собирает пару `Closure` getter/setter. Логика выбора `TypeInterface` для свойства:

1. **`#[Marshal(...)]` атрибут** ([Marshal.php](../../src/Internal/Marshaller/Meta/Marshal.php)) — `name`, `type`, `of`, `nullable`. Можно навесить несколько (берётся первый по порядку).
2. **`TypeFactory::makeRule()`** ([TypeFactory.php:89](../../src/Internal/Marshaller/TypeFactory.php:89)) — пробегает встроенные `RuleFactoryInterface` и возвращает `MarshallingRule`.
3. **`TypeFactory::detect()`** — смотрит `\ReflectionType` свойства и матчит через `DetectableTypeInterface::match`.

## Регистр встроенных типов

Порядок матчинга (важен — первый совпавший побеждает) в [TypeFactory.php:127](../../src/Internal/Marshaller/TypeFactory.php:127):

```
EnumType → EnumValueType → DateTimeType → DateIntervalType
→ UuidType → ArrayType → EncodedCollectionType → ObjectType
```

`ObjectType` стоит последним как catch-all для не-builtin классов.

## EncodedCollectionType

[`EncodedCollectionType`](../../src/Internal/Marshaller/Type/EncodedCollectionType.php) — особенный. Это тот мост, через который `EncodedCollection` свойства DTO становятся proto-сообщениями.

При serialize ([EncodedCollectionType.php:78](../../src/Internal/Marshaller/Type/EncodedCollectionType.php:78)) умеет специализироваться на target-классе через `marshalTo`:

```php
match ($this->marshalTo) {
    SearchAttributes::class => (new SearchAttributes())->setIndexedFields($payloads),
    Memo::class             => (new Memo())->setFields($payloads),
    Payloads::class         => (new Payloads())->setPayloads($payloads),
    Header::class           => (new Header())->setFields($payloads),
    default                 => throw ...,
};
```

Это и есть header/memo conversion path: DTO с `EncodedCollection`-свойством получает корректный proto-message при сериализации.

При parse ([EncodedCollectionType.php:55](../../src/Internal/Marshaller/Type/EncodedCollectionType.php:55)) принимает `null` (→ empty), `array` (→ `fromValues`), или уже-готовый `EncodedCollection` (passthrough).

## Marshal атрибут

[`Marshal`](../../src/Internal/Marshaller/Meta/Marshal.php) extends `MarshallingRule`. Применяется к свойству и переопределяет авто-detection:

```php
class WorkflowInfo
{
    #[Marshal(name: 'TaskQueue')]
    public string $taskQueue;

    #[Marshal(name: 'Memo', of: Memo::class)]
    public ?EncodedCollection $memo = null;
}
```

`Marshal` — `IS_REPEATABLE`: можно навесить несколько rule'ов на одно свойство (полезно для нескольких возможных wire-имён вроде `UserName` / `user_name`). Первый — приоритетный для marshalling, остальные — fallback'и для unmarshalling.

## Обратный путь: ProtoToArrayConverter

[`ProtoToArrayConverter`](../../src/Internal/Marshaller/ProtoToArrayConverter.php) — конвертит proto-Message в обычный hashmap-array, рекурсивно. При этом известные сообщения разворачиваются в SDK-типы:

| proto-Message | → |
|---|---|
| `Timestamp` | `\DateTimeImmutable` |
| `Duration` | `\DateInterval` |
| `SearchAttributes` | `EncodedCollection::fromPayloadCollection($input->getIndexedFields(), $converter)` |
| `Memo` | `EncodedCollection::fromPayloadCollection($input->getFields(), $converter)` |
| `Payloads` | `EncodedValues::fromPayloadCollection($input->getPayloads(), $converter)` |
| `Header` | `Header::fromPayloadCollection($input->getFields(), $converter)` |
| `ScheduleAction`, `UserMetadata` | специальные мапперы |
| остальное | рекурсивный walk через `\ReflectionClass` |

Используется на client-side, когда сервер вернул workflow-result/heartbeat/schedule-state с проторованными полями, и их надо отдать пользовательскому коду в виде PHP-объектов с `EncodedCollection`-свойствами.

## Где Marshaller инжектится глобально

[`WorkerFactory::createMarshaller()`](../../src/WorkerFactory.php:350) собирает один `Marshaller` на воркер-фабрику и кладёт в `ServiceContainer` ([WorkerFactory.php:358](../../src/WorkerFactory.php:358)). Все routes ([InvokeActivity](../../src/Internal/Transport/Router/InvokeActivity.php:75), [StartWorkflow](../../src/Internal/Transport/Router/StartWorkflow.php:65), …) берут его оттуда:

```php
$context = $this->services->marshaller->unmarshal($options, $context);
```

> **Тонкость.** Это **другой канал** сериализации — не payload-канал. `options` приходят от RR в виде уже-десериализованного JSON-array (workflow info, activity options, heartbeat thresholds и т.д.) и заполняют DTO-поля контекста. Payload-канал (args/return) идёт через `EncodedValues` → `DataConverter` → `JsonConverter` → `Marshaller`. То есть `Marshaller` стоит на пересечении обоих каналов.

## См. также

- [Архитектура](architecture.md) — общая картина converter chain.
- [Интеграция](integration.md) — где `JsonConverter` и `Marshaller` реально вызываются.
