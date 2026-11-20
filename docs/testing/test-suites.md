# Виды тестов

В проекте четыре физических test suite'а с разными требованиями к окружению и разной зоной ответственности.

```
tests/
├── Unit/             — изолированные, без внешних зависимостей      (*TestCase.php)
├── Functional/       — поверх temporal-test-server (Java, time-skip) (*TestCase.php)
├── Acceptance/       — поверх реального temporal start-dev           (*Test.php)
│   ├── App/          — runtime + общий код для acceptance'а (не тесты сами по себе)
│   ├── Extra/        — наши тесты «всей нутрянки» SDK
│   └── Harness/      — кросс-SDK тесты Temporal-команды
├── Arch/             — архитектурные правила (отсутствие dump/dd, и т.п.)
└── Nexus/            — Nexus subsystem (Unit/ под suite Unit, *Test.php)
```

## Unit — `tests/Unit/`

Изолированные тесты, не требуют ни Temporal-сервера, ни RR. Запускаются на чистом PHPUnit. Тестируют чистую логику: парсеры, validator'ы, преобразователи, утилиты.

- Файл-суффикс: `*TestCase.php`.
- Bootstrap: общий `tests/bootstrap.php` (специального suite-bootstrap нет).
- Запуск: `composer test:unit`.

Подкаталог `tests/Nexus/Unit/` подцепляется тем же suite Unit'а, но его файлы нарекаются `*Test.php` — это особенность Nexus-subsystem'а.

## Functional — `tests/Functional/`

Тестируют сценарии, требующие настоящего Temporal-сервера, **но** с time-skipping'ом — это нужно когда тест ждёт, скажем, недельный таймер, и не хочет ждать его реально. Используется `temporal-test-server` из Java SDK distribution: он умеет «проматывать» время автоматически, когда workflow висит на таймере или sleep'е.

- Файл-суффикс: `*TestCase.php`.
- Bootstrap: [tests/Functional/bootstrap.php](../../tests/Functional/bootstrap.php) — поднимает `temporal-test-server` через `Environment::startTemporalTestServer()`, потом RR через `Environment::startRoadRunner(...)`. Закрытие — через `register_shutdown_function`.
- Запуск: `composer test:func`.
- Бинарник `temporal-test-server` качается из релизов Java SDK ([testing/src/Downloader.php](../../testing/src/Downloader.php)) при `composer get:binaries`.

## Acceptance — `tests/Acceptance/`

Полный E2E поверх **реального** `temporal start-dev` (без time-skipping'а). Используется когда тест должен проверить реальное поведение (worker versioning, deployment versions, eager workflow start, update'ы и т.п.).

Делится на три подпапки с разной зоной ответственности:

### `App/` — runtime, не тесты

Сборка тестового runtime'а, общие хелперы и базовый `TestCase`. Это **не сами тесты**, а инфраструктура для них. Сюда попадают:

- [tests/Acceptance/App/RuntimeBuilder.php](../../tests/Acceptance/App/RuntimeBuilder.php) — построение `State` (workdir, namespace, address, taskQueues), регистрация feature flags, обнаружение feature-классов.
- [tests/Acceptance/App/Runtime/TemporalStarter.php](../../tests/Acceptance/App/Runtime/TemporalStarter.php) и [RRStarter.php](../../tests/Acceptance/App/Runtime/RRStarter.php) — запуск/остановка `temporal start-dev` и RR.
- [tests/Acceptance/App/TestCase.php](../../tests/Acceptance/App/TestCase.php) — базовый класс с error-handling'ом, history-pretty-print'ом и **RR-restart'ом на падении теста**.

### `Extra/` — наши acceptance тесты

Тесты, которые мы пишем, чтобы проверить «всю нутрянку» SDK: интеграцию workflow / activity / signal / update / query / child-workflow / continue-as-new / schedule / data-converter / interceptor / plugin / nexus / versioning / deployment.

- Файл-суффикс: `*Test.php`.
- Запуск: `composer test:accept` (всё), `composer test:accept-fast`, `composer test:accept-slow` — см. [infrastructure.md](infrastructure.md#разделение-fastslow).

### `Harness/` — cross-SDK тесты команды

Тесты, написанные Temporal-командой (с координацией между SDK на разных языках). Проверяют выполнение спецификации фичи: «такой-то workflow с такими-то signal'ами должен дать такую-то history». Эти тесты **обычно не трогаем**.

- Файл-суффикс: `*Test.php`.
- При обновлении harness'ов — синхронизировать с upstream-репозиторием Temporal-команды, не редактировать локально.

## Arch — `tests/Arch/`

Архитектурные ограничения на код: запрет случайно оставленных `dump()`, `dd()`, `var_dump()`, `print_r()`, проверки структуры пакетов и подобное. Не требуют Temporal-сервера или RR.

- Запуск: `composer test:arch`.
- Дёргать вручную обычно не нужно — это CI-уровень. Хотя можно прогнать локально перед PR.
- Запускается **без** `tests/runner.php`: в [composer.json](../../composer.json) команда — `phpunit --testsuite=Arch`, без runner-обёртки. Suite не нуждается в JUnit-парсинге CI-failure'ов.

## Сводная таблица

| Suite | Где | Сервер | RR | Время | Пишем сами? |
|---|---|---|---|---|---|
| Unit | `tests/Unit` | — | — | мс | да |
| Nexus Unit | `tests/Nexus/Unit` | — | — | мс | да |
| Functional | `tests/Functional` | `temporal-test-server` (time-skip) | да | сек | да |
| Acceptance/Extra | `tests/Acceptance/Extra` | `temporal start-dev` | да | секунды-десятки | да |
| Acceptance/Harness | `tests/Acceptance/Harness` | `temporal start-dev` | да | секунды-десятки | нет, синхронизируем |
| Arch | `tests/Arch` | — | — | мс | редко (само-управляющиеся правила) |

## Файл-нейминг

| Где | Суффикс |
|---|---|
| `tests/Unit/`, `tests/Functional/` | `*TestCase.php` |
| `tests/Acceptance/Extra/`, `tests/Acceptance/Harness/` | `*Test.php` |
| `tests/Nexus/Unit/` | `*Test.php` (исключение в Unit-suite'е) |
| `tests/Arch/` | `*TestCase.php` |

См. также `composer.json:phpunit` config для точного match-pattern'а.

## См. также

- [Инфраструктура запуска](infrastructure.md) — серверы, RR-lifecycle, fast/slow.
- [Testing framework](framework.md) — `testing/` папка как переиспользуемый mini-SDK для тестов.
