# Exception Handling in Interceptors

Начиная с версии 2.16.0, Temporal PHP SDK поддерживает перехват исключений через новые методы в интерфейсах интерцепторов.

## Обзор

Теперь вы можете наблюдать и обрабатывать исключения, возникающие во время выполнения workflow и activity, реализуя следующие методы:
- `WorkflowInboundCallsInterceptor::handleWorkflowException()`
- `ActivityInboundInterceptor::handleActivityException()`

Эти методы вызываются **перед** тем, как исключение будет проброшено дальше, что позволяет вам:
- Логировать исключения с полным контекстом
- Отправлять данные в системы отслеживания ошибок (Sentry, Bugsnag и т.д.)
- Собирать метрики
- Отправлять уведомления

## Базовое использование

### Workflow Exception Interceptor

```php
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Interceptor\Trait\WorkflowInboundCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Workflow;
use Throwable;

class LoggingWorkflowInterceptor implements WorkflowInboundCallsInterceptor
{
    use WorkflowInboundCallsInterceptorTrait;

    public function handleWorkflowException(Throwable $exception, WorkflowInput $input): void
    {
        $info = Workflow::getInfo();
        
        error_log(sprintf(
            '[Workflow Exception] %s в %s (ID: %s, RunID: %s): %s',
            $exception::class,
            $info->type->name,
            $info->execution->getID(),
            $info->execution->getRunID(),
            $exception->getMessage()
        ));
    }
}
```

### Activity Exception Interceptor

```php
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\Trait\ActivityInboundInterceptorTrait;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Activity;
use Throwable;

class LoggingActivityInterceptor implements ActivityInboundInterceptor
{
    use ActivityInboundInterceptorTrait;

    public function handleActivityException(Throwable $exception, ActivityInput $input): void
    {
        $info = Activity::getInfo();
        
        error_log(sprintf(
            '[Activity Exception] %s в %s (ID: %s): %s',
            $exception::class,
            $info->type->name,
            $info->id,
            $exception->getMessage()
        ));
    }
}
```

## Важные примечания

1. **Не выбрасывайте исключения**: Методы `handleException` НЕ должны выбрасывать исключения. Оборачивайте всю логику в try-catch внутри метода.
2. **Производительность**: Старайтесь делать обработку быстрой — она вызывается синхронно в потоке выполнения.
3. **Оригинальное исключение всегда пробрасывается**: Ваш обработчик не предотвращает распространение исключения.
4. **Множественные интерцепторы**: Если вы зарегистрируете несколько интерцепторов, все они будут вызваны по очереди.

## Доступный контекст

### Workflow Context (через `Workflow::getInfo()`)
- `type->name` - имя класса Workflow
- `execution->getID()` - Workflow ID
- `execution->getRunID()` - Current Run ID
- `taskQueue` - имя Task Queue
- `attempt` - номер попытки выполнения

### Activity Context (через `Activity::getInfo()`)
- `type->name` - имя метода Activity
- `id` - Activity ID
- `workflowExecution->getID()` - ID родительского workflow
- `attempt` - номер попытки выполнения
