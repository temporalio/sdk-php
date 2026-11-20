# Testing framework — `testing/`

Папка [testing/](../../testing/) — это переиспользуемый mini-SDK для написания тестов. Он же используется собственными тестами SDK через `tests/`, и его же могут использовать сторонние пакеты, тестирующие свои workflow'ы / activity'и поверх Temporal PHP SDK.

Пакет публикуется как `temporal/testing`.

## Содержимое

```
testing/src/
├── Environment.php             — управление temporal-server'ами и RR
├── Command.php                 — параметры запуска (workdir, namespace, address, TLS)
├── Downloader.php              — скачивание temporal-test-server из Java-релизов
├── SystemInfo.php              — определение OS/arch для выбора binary
├── TestService.php             — низкоуровневая обёртка
├── ActivityMocker.php          — мок активити для unit-тестов workflow'а
├── WorkerMock.php              — фейк-воркер
├── WorkerFactory.php           — фабрика для тестового воркера
├── WorkflowTestCase.php        — базовый PHPUnit-класс с замоканным runtime'ом
├── WithoutTimeSkipping.php     — отключатель time-skip'а в скоупе
├── DeprecationCollector.php    — собирает PHP-deprecations за время теста
├── DeprecationMessage.php      — DTO для записи deprecation'а
├── Replay/                     — replay-тесты workflow'ов из history
└── Support/                    — внутренние хелперы
```

## Главные кубики

### `Environment` — оркестратор внешних процессов

[testing/src/Environment.php](../../testing/src/Environment.php). Единый фасад для запуска/остановки `temporal start-dev`, `temporal-test-server` и RoadRunner'а. Используется и в `tests/Functional/bootstrap.php`, и в `tests/Acceptance/App/Runtime/`.

Ключевые методы:

| Метод | Что делает |
|---|---|
| `Environment::create()` | Создание |
| `startTemporalServer(...)` | Запуск **реального** `temporal start-dev` с custom dynamic-config-флагами и search attributes |
| `startTemporalTestServer(...)` | Запуск **Java time-skipping** test-server'а |
| `startRoadRunner(...)` | Запуск RR с указанной командой/конфигом |
| `stopTemporalServer()` / `stopTemporalTestServer()` / `stopRoadRunner()` | Остановки |
| `stop()` | Останавливает всё (один shutdown handler) |
| `isTemporalRunning()` / `isTemporalTestRunning()` / `isRoadRunnerRunning()` | Статусы |

`startRoadRunner()` ([Environment.php:212](../../testing/src/Environment.php:212)) проверяет, что **temporal-сервер уже поднят** перед стартом RR — иначе RR упадёт на попытке подключения. Если ни один сервер не запущен — exception.

### `Downloader` — скачивание test-server'а

[testing/src/Downloader.php](../../testing/src/Downloader.php). Тянет `temporal-test-server` из релизов **Java SDK** (не из основного Temporal CLI релиза — там его нет). URL: `https://api.github.com/repos/temporalio/sdk-java/releases/...`.

Архив именуется `temporal-test-server_<version>_<os>_<arch>.{zip,tar.gz}`. Файл-pattern в коде: `temporal-test-server_[^_]+_([^_]+)_([^.]+)\.(?:zip|tar.gz)$`.

### `Command` — параметры запуска

[testing/src/Command.php](../../testing/src/Command.php). Иммутабельный VO с workDir, namespace, temporal address, TLS-параметрами, дополнительными CLI-аргументами PHP-binary и worker-script'у. Передаётся в `Environment` и `RuntimeBuilder`.

### `WorkflowTestCase` — для unit-тестов workflow'ов

[testing/src/WorkflowTestCase.php](../../testing/src/WorkflowTestCase.php) — базовый класс. Используется когда тестируется чистый workflow-код (без запуска Temporal-сервера); полагается на `WorkerMock` + `ActivityMocker` для in-process симуляции. Подходит для проверки decision-логики workflow'а: «при таком input'е workflow должен вызвать activity X с такими аргументами».

Это **не** замена acceptance-теста — `WorkflowTestCase` не покрывает реальные history-events, retry-policy сервера, time-skipping и т.п. Зато даёт мс-уровень на test и не требует Temporal-сервера.

### `ActivityMocker`, `WorkerMock`

[testing/src/ActivityMocker.php](../../testing/src/ActivityMocker.php), [testing/src/WorkerMock.php](../../testing/src/WorkerMock.php). Хелперы для in-process тестирования. Activity подменяется на closure, worker — на in-memory диспетчер.

### `Replay/` — тесты на history

[testing/src/Replay/](../../testing/src/Replay/) — `WorkflowReplayer` и поддерживающий код. Берёт сохранённую history workflow'а (JSON-export от Temporal сервера или CLI), прогоняет через PHP-воркер и валидирует, что ничего не сломалось.

Это критично для regression-тестирования: если изменение в workflow-коде ломает детерминизм, replay-тест по старой history упадёт с nondeterminism error.

### `WithoutTimeSkipping`

[testing/src/WithoutTimeSkipping.php](../../testing/src/WithoutTimeSkipping.php) — RAII-обёртка: на время своей жизни в скоупе **отключает** time-skipping в `temporal-test-server`'е (для тестов, где надо проверить именно реальное ожидание). Полезно когда weak-тест-server слишком торопится.

## Когда что использовать

| Сценарий | Что брать |
|---|---|
| Юзер тестирует свой workflow, без Temporal-сервера | `WorkflowTestCase` + `ActivityMocker` |
| Проверить что workflow не сломал детерминизм после правок | `WorkflowReplayer` (Replay/) с сохранённой history |
| Проверить interaction'ы поверх настоящего Temporal в CI | `Environment::startTemporalTestServer` (если ОК time-skip) или `startTemporalServer` (если нужны server-side фичи) + `startRoadRunner` |
| Проверить server-side фичи (versioning, deployment, schedule) | Только `startTemporalServer` (`temporal start-dev`) |

## Зависимость от Java для `temporal-test-server`

`temporal-test-server` — это **не** PHP и не Go binary. Это standalone-релиз из Java SDK с time-skipping'ом, написанный на Java и упакованный как native-binary через GraalVM. Качается отдельно от основного Temporal CLI. Это стоит держать в голове при отладке проблем с Functional-suite'ом — если test-server-binary не качается, проблема может быть в Java-релизах (rate-limit GitHub API, недоступность асссета для конкретной OS+arch).

## См. также

- [Виды тестов](test-suites.md) — какой test suite использует какие хелперы.
- [Инфраструктура](infrastructure.md) — как именно `Environment` оркестрирует процессы в bootstrap'ах.
