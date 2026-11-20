# Плагины (Plugins)

Плагины — это механизм расширения Temporal PHP SDK, позволяющий модифицировать поведение клиентов, воркеров и соединений без изменения прикладного кода. В отличие от интерцепторов, которые работают на уровне отдельных вызовов, плагины конфигурируют инфраструктуру SDK целиком: подключение, сериализацию, опции воркеров, пайплайн интерцепторов и жизненный цикл фабрики.

## Обзор

Система плагинов предоставляет 4 уровня интеграции:

| Уровень | Интерфейс | Что настраивает |
|---------|-----------|-----------------|
| Соединение | `ConnectionPluginInterface` | gRPC-клиент (API-ключи, метаданные, TLS) |
| Клиент | `ClientPluginInterface` | `WorkflowClient` (namespace, конвертеры, интерцепторы) |
| Schedule-клиент | `ScheduleClientPluginInterface` | `ScheduleClient` (namespace, конвертеры) |
| Воркер | `WorkerPluginInterface` | `WorkerFactory` и воркеры (опции, интерцепторы, жизненный цикл) |

Плагин может реализовывать один или несколько интерфейсов. Один плагин, реализующий `ClientPluginInterface` и `WorkerPluginInterface`, автоматически пропагируется из клиента в фабрику воркеров.

## Быстрый старт

Минимальный плагин, добавляющий интерцептор к `WorkflowClient`:

```php
use Temporal\Plugin\ClientPluginInterface;
use Temporal\Plugin\ClientPluginContext;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Interceptor\Trait\WorkflowClientCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Workflow\WorkflowExecution;

class LoggingPlugin implements ClientPluginInterface
{
    public function getName(): string
    {
        return 'my-app.logging';
    }

    public function configureClient(ClientPluginContext $context): void
    {
        $context->addInterceptor(new LoggingInterceptor());
    }
}

class LoggingInterceptor implements WorkflowClientCallsInterceptor
{
    use WorkflowClientCallsInterceptorTrait;

    public function start(StartInput $input, callable $next): WorkflowExecution
    {
        echo "Starting workflow: {$input->workflowType}\n";
        return $next($input);
    }
}
```

Подключение:

```php
use Temporal\Client\WorkflowClient;
use Temporal\Plugin\PluginRegistry;

$client = WorkflowClient::create(
    serviceClient: $serviceClient,
    pluginRegistry: new PluginRegistry([new LoggingPlugin()]),
);
```

## Интерфейсы плагинов

### `PluginInterface`

Базовый интерфейс. Единственное требование — уникальное имя для дедупликации.

```php
interface PluginInterface
{
    public function getName(): string;
}
```

Рекомендуемая конвенция именования: обратная доменная нотация (`my-org.tracing`, `acme.cloud-auth`).

### `ConnectionPluginInterface`

Конфигурирует gRPC-соединение. Вызывается **первым**, до клиентских плагинов.

```php
interface ConnectionPluginInterface
{
    public function getName(): string;
    public function configureServiceClient(ConnectionPluginContext $context): void;
}
```

Пример — автоматическая установка API-ключа:

```php
class CloudAuthPlugin implements ConnectionPluginInterface
{
    public function __construct(
        private readonly string $apiKey,
    ) {}

    public function getName(): string
    {
        return 'temporal.cloud-auth';
    }

    public function configureServiceClient(ConnectionPluginContext $context): void
    {
        $context->setServiceClient(
            $context->getServiceClient()->withAuthKey($this->apiKey),
        );
    }
}
```

### `ClientPluginInterface`

Конфигурирует `WorkflowClient`: namespace, `DataConverter`, интерцепторы.

```php
interface ClientPluginInterface extends PluginInterface
{
    public function configureClient(ClientPluginContext $context): void;
}
```

### `ScheduleClientPluginInterface`

Конфигурирует `ScheduleClient`. Автоматически пропагируется из `WorkflowClient`, если плагин реализует оба интерфейса.

```php
interface ScheduleClientPluginInterface extends PluginInterface
{
    public function configureScheduleClient(ScheduleClientPluginContext $context): void;
}
```

### `WorkerPluginInterface`

Самый мощный интерфейс с 4 хуками:

```php
interface WorkerPluginInterface extends PluginInterface
{
    public function configureWorkerFactory(WorkerFactoryPluginContext $context): void;
    public function configureWorker(WorkerPluginContext $context): void;
    public function initializeWorker(WorkerInterface $worker): void;
    public function run(WorkerFactoryInterface $factory, callable $next): int;
}
```

| Метод | Когда вызывается | Что можно делать |
|-------|------------------|------------------|
| `configureWorkerFactory` | При создании `WorkerFactory` | Изменить `DataConverter` для всех воркеров |
| `configureWorker` | При вызове `newWorker()`, до создания воркера | Изменить `WorkerOptions`, добавить интерцепторы |
| `initializeWorker` | После создания воркера | Зарегистрировать Workflow/Activity |
| `run` | При вызове `WorkerFactory::run()` | Обернуть жизненный цикл (ресурсы, мониторинг) |

### `TemporalPluginInterface`

Объединяет все 4 интерфейса. Удобен для плагинов, работающих на всех уровнях:

```php
interface TemporalPluginInterface extends
    ConnectionPluginInterface,
    ClientPluginInterface,
    ScheduleClientPluginInterface,
    WorkerPluginInterface
{
}
```

## AbstractPlugin

Базовый класс с no-op реализациями всех методов. Рекомендуется для плагинов, которым нужно несколько хуков:

```php
use Temporal\Plugin\AbstractPlugin;
use Temporal\Plugin\ClientPluginContext;
use Temporal\Plugin\WorkerPluginContext;

class MyPlugin extends AbstractPlugin
{
    public function __construct()
    {
        parent::__construct('my-org.my-plugin');
    }

    public function configureClient(ClientPluginContext $context): void
    {
        // Только нужные хуки, остальные — no-op
    }

    public function configureWorker(WorkerPluginContext $context): void
    {
        // ...
    }
}
```

## Трейты

Для выборочной реализации отдельных интерфейсов используйте трейты с no-op методами:

| Трейт | Для интерфейса | No-op методы |
|-------|----------------|-------------|
| `ConnectionPluginTrait` | `ConnectionPluginInterface` | `configureServiceClient()` |
| `ClientPluginTrait` | `ClientPluginInterface` | `configureClient()` |
| `ScheduleClientPluginTrait` | `ScheduleClientPluginInterface` | `configureScheduleClient()` |
| `WorkerPluginTrait` | `WorkerPluginInterface` | `configureWorkerFactory()`, `configureWorker()`, `initializeWorker()`, `run()` |

Пример — плагин только для клиента и воркера:

```php
use Temporal\Plugin\ClientPluginInterface;
use Temporal\Plugin\ClientPluginTrait;
use Temporal\Plugin\WorkerPluginInterface;
use Temporal\Plugin\WorkerPluginTrait;

class MyComboPlugin implements ClientPluginInterface, WorkerPluginInterface
{
    use ClientPluginTrait;  // no-op configureClient()
    use WorkerPluginTrait;  // no-op для всех 4 методов

    public function getName(): string
    {
        return 'my-combo';
    }

    // Переопределяем только нужные методы
    public function configureClient(ClientPluginContext $context): void
    {
        $context->addInterceptor(new MyInterceptor());
    }
}
```

## Контексты

Каждый хук получает контекст — mutable-объект с fluent API для модификации конфигурации.

### `ConnectionPluginContext`

```php
$context->getServiceClient(): ServiceClientInterface;
$context->setServiceClient(ServiceClientInterface $client): self;
```

### `ClientPluginContext`

```php
$context->getClientOptions(): ClientOptions;
$context->setClientOptions(ClientOptions $options): self;

$context->getDataConverter(): ?DataConverterInterface;
$context->setDataConverter(?DataConverterInterface $converter): self;

$context->getInterceptors(): array;
$context->setInterceptors(array $interceptors): self;
$context->addInterceptor(Interceptor $interceptor): self;
```

### `WorkerFactoryPluginContext`

```php
$context->getDataConverter(): ?DataConverterInterface;
$context->setDataConverter(?DataConverterInterface $converter): self;
```

### `WorkerPluginContext`

```php
$context->getTaskQueue(): string;  // только чтение

$context->getWorkerOptions(): WorkerOptions;
$context->setWorkerOptions(WorkerOptions $options): self;

$context->getExceptionInterceptor(): ?ExceptionInterceptorInterface;
$context->setExceptionInterceptor(?ExceptionInterceptorInterface $interceptor): self;

$context->getInterceptors(): array;
$context->setInterceptors(array $interceptors): self;
$context->addInterceptor(Interceptor $interceptor): self;
```

### `ScheduleClientPluginContext`

```php
$context->getClientOptions(): ClientOptions;
$context->setClientOptions(ClientOptions $options): self;

$context->getDataConverter(): ?DataConverterInterface;
$context->setDataConverter(?DataConverterInterface $converter): self;
```

## Жизненный цикл

Плагины вызываются в порядке регистрации. Полный жизненный цикл:

```
WorkflowClient::__construct()
│
├─ 1. ConnectionPluginInterface::configureServiceClient()
│     Настройка gRPC-соединения (API-ключи, метаданные)
│
├─ 2. ClientPluginInterface::configureClient()
│     Настройка клиента (namespace, конвертеры, интерцепторы)
│
WorkerFactory::__construct()
│
├─ 3. Merge: плагины из клиента ($client->getWorkerPlugins()) добавляются в фабрику
│
├─ 4. WorkerPluginInterface::configureWorkerFactory()
│     Настройка фабрики (DataConverter для всех воркеров)
│
WorkerFactory::newWorker()  ← вызывается для каждого воркера
│
├─ 5. WorkerPluginInterface::configureWorker()
│     Настройка воркера (WorkerOptions, интерцепторы)
│
├─ 6. Создание Worker
│
├─ 7. WorkerPluginInterface::initializeWorker()
│     Пост-инициализация (регистрация Workflow/Activity)
│
WorkerFactory::run()
│
└─ 8. WorkerPluginInterface::run()
      Chain-of-responsibility обёртка жизненного цикла
```

## Пропагация плагинов

Плагины автоматически передаются между компонентами SDK:

### Client → WorkerFactory

Плагины, реализующие `WorkerPluginInterface`, автоматически пропагируются из `WorkflowClient` в `WorkerFactory`:

```php
$registry = new PluginRegistry([new MyFullPlugin()]);

// Плагин регистрируется на клиенте
$client = WorkflowClient::create($serviceClient, pluginRegistry: $registry);

// Плагин автоматически пропагируется в фабрику
$factory = WorkerFactory::create(client: $client);
// MyFullPlugin::configureWorkerFactory() вызван автоматически
```

### Client → ScheduleClient

Плагины, реализующие `ScheduleClientPluginInterface`, доступны через `$client->getScheduleClientPlugins()`.

### Объединение плагинов из разных источников

Фабрика может получать плагины из нескольких источников. Порядок: сначала плагины фабрики, затем — плагины из клиента:

```php
$client = WorkflowClient::create($serviceClient, pluginRegistry: new PluginRegistry([$clientPlugin]));

$factory = WorkerFactory::create(
    pluginRegistry: new PluginRegistry([$factoryPlugin]),
    client: $client,
);
// Порядок: $factoryPlugin → $clientPlugin
```

Дубликаты имён приводят к исключению `RuntimeException`.

## PluginRegistry

`PluginRegistry` управляет коллекцией плагинов с дедупликацией по имени.

```php
use Temporal\Plugin\PluginRegistry;

// Создание с начальным набором
$registry = new PluginRegistry([$plugin1, $plugin2]);

// Добавление одного плагина
$registry->add($plugin3);

// Слияние нескольких плагинов
$registry->merge([$plugin4, $plugin5]);

// Получение плагинов по интерфейсу
$clientPlugins = $registry->getPlugins(ClientPluginInterface::class);
$workerPlugins = $registry->getPlugins(WorkerPluginInterface::class);
```

Дубликаты:

```php
$registry = new PluginRegistry([new MyPlugin(), new MyPlugin()]);
// RuntimeException: Duplicate plugin "my-plugin": a plugin with this name is already registered.
```

## Run hook

Метод `run()` в `WorkerPluginInterface` использует паттерн chain-of-responsibility. Первый зарегистрированный плагин является внешней обёрткой.

### Управление ресурсами (try/finally)

```php
public function run(WorkerFactoryInterface $factory, callable $next): int
{
    $connection = ConnectionPool::create();
    try {
        return $next($factory); // вызов следующего плагина или основного цикла
    } finally {
        $connection->close(); // гарантированная очистка
    }
}
```

### Порядок вызова

При двух плагинах (A, B зарегистрированы в этом порядке):

```
A::run() before
  B::run() before
    --- основной цикл ---
  B::run() after (finally)
A::run() after (finally)
```

### Прерывание цепочки

Плагин может не вызывать `$next()`, прерывая выполнение:

```php
public function run(WorkerFactoryInterface $factory, callable $next): int
{
    if (!$this->isReady()) {
        return 1; // Прервать без запуска основного цикла
    }

    return $next($factory);
}
```

## Полный пример

Плагин для аутентификации через Temporal Cloud — настраивает API-ключ на уровне соединения и namespace на уровне клиента:

```php
use Temporal\Plugin\AbstractPlugin;
use Temporal\Plugin\ConnectionPluginContext;
use Temporal\Plugin\ClientPluginContext;
use Temporal\Client\ClientOptions;

class TemporalCloudPlugin extends AbstractPlugin
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $namespace,
    ) {
        parent::__construct('temporal.cloud');
    }

    public function configureServiceClient(ConnectionPluginContext $context): void
    {
        $context->setServiceClient(
            $context->getServiceClient()->withAuthKey($this->apiKey),
        );
    }

    public function configureClient(ClientPluginContext $context): void
    {
        $context->setClientOptions(
            (new ClientOptions())->withNamespace($this->namespace),
        );
    }
}
```

Использование:

```php
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Plugin\PluginRegistry;

$plugin = new TemporalCloudPlugin(
    apiKey: 'my-api-key',
    namespace: 'my-namespace.a1b2c',
);

$client = WorkflowClient::create(
    serviceClient: ServiceClient::createSSL('my-namespace.a1b2c.tmprl.cloud:7233'),
    pluginRegistry: new PluginRegistry([$plugin]),
);
```
