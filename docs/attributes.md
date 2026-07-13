# Атрибуты опций (Attributes for Options)

В Temporal PHP SDK вы можете задавать параметры для Workflow и Activity с помощью атрибутов прямо в коде интерфейсов или классов. Это позволяет определить значения по умолчанию, которые будут использоваться при создании стабов, если они не переопределены явно.

## Гранулярные атрибуты Activity

Для настройки Activity используются гранулярные атрибуты из пространства имен `Temporal\Activity\Attribute`.

### Список доступных атрибутов Activity:

- `#[TaskQueue(string $name)]`
- `#[ScheduleToCloseTimeout(int|string|DateInterval $timeout)]`
- `#[ScheduleToStartTimeout(int|string|DateInterval $timeout)]`
- `#[StartToCloseTimeout(int|string|DateInterval $timeout)]`
- `#[HeartbeatTimeout(int|string|DateInterval $timeout)]`
- `#[CancellationType(ActivityCancellationType|int $type)]`
- `#[RetryPolicy(RetryOptions $options)]` (или именованный конструктор `RetryPolicy::new(...)`)
- `#[ActivityPriority(Priority|int $priority)]`
- `#[Summary(string $text)]`

### Пример использования Activity:

```php
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\Attribute\ScheduleToCloseTimeout;
use Temporal\Activity\Attribute\StartToCloseTimeout;
use Temporal\Activity\Attribute\TaskQueue;
use Temporal\Activity\Attribute\RetryPolicy;
use Temporal\Common\RetryOptions;

#[TaskQueue('my-task-queue')]
#[ScheduleToCloseTimeout('10 seconds')]
#[RetryPolicy(new RetryOptions(maximumAttempts: 5))]
#[ActivityInterface(prefix: 'App.')]
interface MyActivityInterface
{
    #[ActivityMethod]
    #[StartToCloseTimeout('2 seconds')] // Переопределяет или дополняет опции класса
    public function doSomething();
}
```

В Workflow:
```php
// Будут использованы все опции из атрибутов
$activity = Workflow::newActivityStub(MyActivityInterface::class);

// Атрибуты будут дополнены/перекрыты опцией из кода
$activity = Workflow::newActivityStub(
    MyActivityInterface::class,
    ActivityOptions::new()->withTaskQueue('override-queue')
);
```

---

## Гранулярные атрибуты Workflow

Аналогично Activity, для Workflow доступны гранулярные атрибуты в пространстве имен `Temporal\Workflow\Attribute`.

### Список доступных атрибутов Workflow:

- `#[WorkflowId(string $id)]`
- `#[TaskQueue(string $name)]`
- `#[WorkflowExecutionTimeout(int|string|DateInterval $timeout)]`
- `#[WorkflowRunTimeout(int|string|DateInterval $timeout)]`
- `#[WorkflowTaskTimeout(int|string|DateInterval $timeout)]`
- `#[WorkflowIdReusePolicy(IdReusePolicy|int $policy)]`
- `#[WorkflowIdConflictPolicy(WorkflowIdConflictPolicy $policy)]`
- `#[CronSchedule(string $expression)]`
- `#[RetryPolicy(RetryOptions $options)]`
- `#[WorkflowPriority(Priority|int $priority)]`
- `#[Summary(string $text)]`

### Пример использования Workflow:

```php
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\Attribute\TaskQueue;
use Temporal\Workflow\Attribute\WorkflowRunTimeout;

#[TaskQueue('reports-queue')]
#[WorkflowRunTimeout('1 hour')]
#[WorkflowInterface]
interface MyWorkflowInterface
{
    #[WorkflowMethod]
    public function execute();
}
```

## Приоритет применения (Precedence)

Опции мерджатся в следующем порядке (каждый следующий уровень перекрывает предыдущий):
1. Родительский класс (интерфейс)
2. Текущий класс (интерфейс)
3. Метод
4. Опции, переданные явно при создании стаба (`Workflow::newActivityStub` или `WorkflowClient::newWorkflowStub`)
