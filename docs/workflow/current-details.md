# Current Details

**Current Details** — это механизм в Temporal для динамического обновления описания или статуса Workflow прямо во время его выполнения.

## Обзор

В отличие от статических Summary или Details, которые устанавливаются при запуске и не меняются, Current Details можно обновлять многократно. Это значение отображается в Temporal UI и CLI, позволяя пользователям видеть текущий прогресс или состояние Workflow без выполнения специальных Query запросов.

### Основные возможности:
- Динамическое обновление во время выполнения.
- Поддержка **Temporal Markdown** (жирный текст, списки, ссылки и т.д.).
- Поддержка многострочного текста.
- Отображение в Temporal UI в поле "Current details".

## Использование в Workflow

Для работы с Current Details используйте статические методы класса `Temporal\Workflow`.

### Установка деталей

Метод `setCurrentDetails` отправляет команду на обновление статуса. Рекомендуется использовать `yield`, чтобы дождаться подтверждения отправки команды.

```php
use Temporal\Workflow;

#[WorkflowInterface]
class OrderProcessingWorkflow
{
    #[WorkflowMethod]
    public function process(Order $order): Generator
    {
        // Установка начального статуса
        yield Workflow::setCurrentDetails("📋 Заказ получен: #{$order->id}");
        
        // Валидация
        yield Workflow::setCurrentDetails("🔍 Валидация заказа...");
        yield Workflow::executeActivity('validateOrder', [$order]);
        
        // Обработка платежа
        yield Workflow::setCurrentDetails("💳 Обработка платежа...");
        $payment = yield Workflow::executeActivity('processPayment', [$order]);
        
        // Финальный статус с использованием Markdown
        yield Workflow::setCurrentDetails(
            "✅ Заказ успешно обработан!\n\n" .
            "**ID заказа:** {$order->id}\n" .
            "**Сумма:** \${$payment->amount}"
        );
        
        return $payment;
    }
}
```

### Получение текущих деталей

Вы можете получить последнее установленное значение в рамках текущего выполнения:

```php
$currentStatus = Workflow::getCurrentDetails();
```

## Использование в интерцепторах

Вы можете перехватывать вызовы `setCurrentDetails` с помощью `WorkflowOutboundCallsInterceptor`.

```php
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;
use Temporal\Interceptor\WorkflowOutboundCalls\SetCurrentDetailsInput;

class MyInterceptor implements WorkflowOutboundCallsInterceptor
{
    use WorkflowOutboundCallsInterceptorTrait;

    public function setCurrentDetails(SetCurrentDetailsInput $input, callable $next): PromiseInterface
    {
        // Можно модифицировать текст перед отправкой
        $modifiedInput = $input->with(
            details: "[LOG] " . $input->details
        );
        
        return $next($modifiedInput);
    }
}
```

## Ограничения и требования

- **Версия RoadRunner**: Требуется версия **2025.2.0** или выше.
- **Безопасность**: Изображения, произвольный HTML и скрипты не поддерживаются в целях безопасности.

## Поддержка Markdown

Current Details поддерживает базовый синтаксис Temporal Markdown:
- `# Заголовок`
- `**Жирный текст**`
- `- Списки`
- `[Ссылки](https://temporal.io)`
