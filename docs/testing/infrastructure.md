# Инфраструктура тестов

Как тесты сами поднимают Temporal-сервер и RoadRunner, как разделяются fast/slow и что делает `tests/runner.php`.

## Auto-bootstrap по suite

Корневой [tests/bootstrap.php](../../tests/bootstrap.php) определяет имя suite'а из `--testsuite=`, `--filter=` или path-аргумента в `argv`, и подключает соответствующий suite-bootstrap:

```
tests/bootstrap.php
  ├─ tests/Unit/bootstrap.php          (нет — Unit'у bootstrap не нужен)
  ├─ tests/Functional/bootstrap.php    → temporal-test-server + RR
  └─ tests/Acceptance/bootstrap.php    → temporal start-dev + RR
```

Если bootstrap для suite'а не найден — просто continue, никакой инициализации.

Suite-имя вычисляется так: `Acceptance-Fast` → `Acceptance` (всё после `-` отбрасывается). То есть `test:accept-fast` и `test:accept-slow` тоже подцепляют общий `Acceptance/bootstrap.php`.

## Сценарий запуска

### Functional

[tests/Functional/bootstrap.php](../../tests/Functional/bootstrap.php) делает:

1. `Environment::startTemporalTestServer()` — поднимает `temporal-test-server` (Java-binary, time-skipping).
2. Регистрирует системные search attributes через `SearchAttributeTestInvoker`.
3. `Environment::startRoadRunner(...)` — поднимает RR с конфигом `tests/Functional/.rr.silent.yaml` и worker-script'ом `tests/Functional/worker.php`.
4. `register_shutdown_function(fn() => $environment->stop())` — гарантирует остановку обоих процессов при выходе.

### Acceptance

[tests/Acceptance/bootstrap.php](../../tests/Acceptance/bootstrap.php) делает:

1. `RuntimeBuilder::init()` — выставляет error reporting, регистрирует `DeprecationCollector`, выставляет feature flags ([RuntimeBuilder.php:91-100](../../tests/Acceptance/App/RuntimeBuilder.php:91)).
2. Создаёт `Environment::create()`.
3. Строит `State` через `RuntimeBuilder::createEmpty()` со списком namespace'ов под `Harness` и `Extra`.
4. Создаёт `TemporalStarter` и `RRStarter`, запускает их.

`TemporalStarter::start()` ([tests/Acceptance/App/Runtime/TemporalStarter.php](../../tests/Acceptance/App/Runtime/TemporalStarter.php)) поднимает `temporal start-dev` с набором dynamic-config-флагов (включает update API, async-accepted, multi-operation, eager-start, deployment versions и др.) и pre-зарегистрированными search attributes (`foo`, `bar`, `testBool`, …).

`RRStarter::start()` ([tests/Acceptance/App/Runtime/RRStarter.php](../../tests/Acceptance/App/Runtime/RRStarter.php)) собирает командную строку RR с `-w <rrConfigDir> -o temporal.namespace=... -o temporal.address=... -o server.command=...` и зовёт `Environment::startRoadRunner(...)`.

## Жизненный цикл серверов

| Что | Когда стартует | Когда останавливается |
|---|---|---|
| `temporal-test-server` (Functional) | в bootstrap'е | через `register_shutdown_function` |
| `temporal start-dev` (Acceptance) | в bootstrap'е | через `register_shutdown_function` в `TemporalStarter::__construct` |
| RoadRunner (Functional) | сразу после temporal-test-server | через `register_shutdown_function` |
| RoadRunner (Acceptance) | сразу после temporal-dev | через `register_shutdown_function` в `RRStarter::__construct`, плюс **рестарт после fail'а теста** |

## Restart RR на fail теста

[tests/Acceptance/App/TestCase.php:117-122](../../tests/Acceptance/App/TestCase.php:117):

```php
if (!$e instanceof SkippedTest) {
    // Restart RR if a Error occurs
    $roadRunnerStarter = $container->get(RRStarter::class);
    $roadRunnerStarter->stop();
    $roadRunnerStarter->start();
}
```

Базовый acceptance-`TestCase` оборачивает `runTest()` в try/catch. Если тест упал по любой причине **кроме `SkippedTest`** — RR полностью перезапускается. Зачем:

- В случае фейла worker мог остаться в неконсистентном состоянии: workflow в подвешенном виде, неосвобождённые ресурсы, утечка состояния между тестами.
- Перезапуск гарантирует что **следующий тест получит чистый воркер**, и фейл одного теста не каскадирует в фейлы остальных.
- `SkippedTest` исключён, потому что skip — это не фейл выполнения, ничего не сломалось.

`temporal start-dev` при этом **не** перезапускается — он живёт всё время до конца suite'а.

## Разделение fast/slow

`composer test:accept-fast` и `composer test:accept-slow` запускают разные подсуиты, чтобы локально не ждать долгие тесты. Список slow-файлов **не задаётся вручную**, а **генерируется** скриптом.

### Команда генерации

```bash
php tests/phpunit-generate.php
```

[tests/phpunit-generate.php](../../tests/phpunit-generate.php) делает:

1. Читает JUnit-логи из:
   - `runtime/phpunit-acceptance-junit.xml`
   - `runtime/phpunit-acceptance-fast-junit.xml`
   - `runtime/phpunit-acceptance-slow-junit.xml`
2. Для каждого testcase берёт `time` (длительность) и группирует по файлу — оставляет **максимум** среди методов файла.
3. Если max-time файла ≥ `--threshold` (по умолчанию `1.0` сек) → файл попадает в `slow`, иначе в `fast`.
4. Перезаписывает `phpunit.xml.dist` (или путь из `argv[2]`):
   - `Acceptance-Fast` suite — `<exclude>` со slow-файлами, `<file>` с остальными;
   - `Acceptance-Slow` suite — `<file>` со slow-файлами.

То есть **процедура такая**: один раз прогнать полный `composer test:accept`, чтобы получить JUnit-логи, потом запустить `phpunit-generate.php` — и `phpunit.xml.dist` обновится. Дальше `test:accept-fast` будет быстрее.

### Threshold

Дефолт — 1 секунда. Можно переопределить:

```bash
php tests/phpunit-generate.php --threshold=2.5
```

## `tests/runner.php` — обёртка для CI

[tests/runner.php](../../tests/runner.php) — wrapper вокруг PHPUnit, парсит JUnit-лог и обрабатывает edge-case CI: PHPUnit может вернуть exit-code `0` даже когда тест упал из-за внутренней ошибки. runner проверяет JUnit-output и форсит exit-code в случае таких ошибок.

В composer-скриптах все suite'ы кроме `arch` запускаются через runner:

```json
"test:unit":   "tests/runner.php vendor/bin/phpunit --testsuite=Unit ...",
"test:func":   "tests/runner.php vendor/bin/phpunit --testsuite=Functional ...",
"test:accept": "tests/runner.php vendor/bin/phpunit --testsuite=Acceptance ...",
"test:arch":   "phpunit --testsuite=Arch ..."
```

Arch без runner'а потому что не требует CI-special'а.

## Конкретный тест

```bash
# Unit
vendor/bin/phpunit --testsuite=Unit --filter=SomeTestCase

# Functional (нужен сервер — runner.php подцепит bootstrap)
tests/runner.php vendor/bin/phpunit --testsuite=Functional --filter=SomeTestCase

# Acceptance
tests/runner.php vendor/bin/phpunit --testsuite=Acceptance --filter=SomeTest
```

`bootstrap.php` сам определит suite по `--filter` или path-аргументу — поэтому если запускать тест без `--testsuite`, но с filter'ом матчащим `Temporal\Tests\Acceptance\...`, инфраструктура всё равно поднимется.

## Бинарники

```bash
composer get:binaries
```

[composer.json:95-98](../../composer.json:95) — два шага:

1. `dload get` (через [internal/dload](https://github.com/spiral/dload)) — качает RoadRunner и Temporal CLI / temporal-test-server.
2. `RoadRunnerVersionChecker::postUpdate` — пост-update проверка совместимости версии RR с SDK.

`temporal-test-server` качается из релизов Java SDK ([testing/src/Downloader.php:13](../../testing/src/Downloader.php:13)) по URL `https://api.github.com/repos/temporalio/sdk-java/releases/...`. Он **не** идёт в Temporal-CLI релизе, поэтому отдельный downloader.

## Переменные окружения

| Var | Где используется | Назначение |
|---|---|---|
| `TEMPORAL_ADDRESS` | bootstrap'ы и тесты | По умолчанию `127.0.0.1:7233`; для acceptance — обычно реальный сервер |
| `RR_CODEC` | RR-конфиг и worker-script | `proto` (default) или `json` |
| `ACTIVITY_WORKERS` | Acceptance bootstrap | Сколько worker-процессов поднять (default `2`) |

## См. также

- [Виды тестов](test-suites.md) — какой suite за что отвечает.
- [Testing framework](framework.md) — `testing/` mini-SDK, на котором всё это построено.
- [Архитектура runtime'а](../runtime/architecture.md) — как RR общается с PHP вообще.
