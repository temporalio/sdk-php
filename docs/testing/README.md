# Тестирование

Документация по тестам Temporal PHP SDK: какие suite'ы есть, как они запускаются, какую инфраструктуру поднимают и какой mini-фреймворк используют.

## Содержание

| Документ | О чём |
|---|---|
| [Виды тестов](test-suites.md) | Unit / Functional / Acceptance (App + Extra + Harness) / Arch — кто за что отвечает, file-naming, наши vs upstream |
| [Инфраструктура](infrastructure.md) | Auto-bootstrap по suite, lifecycle серверов и RR, restart RR на fail, fast/slow генерация, `tests/runner.php`, `composer get:binaries` |
| [Testing framework](framework.md) | `testing/` — `Environment`, `WorkflowTestCase`, `ActivityMocker`, `Replay`, `Downloader` |

## Quick reference

```bash
# Запуск
composer test:unit            # быстрые unit-тесты, без серверов
composer test:func            # functional, поверх temporal-test-server (time-skip)
composer test:accept          # все acceptance, поверх temporal start-dev
composer test:accept-fast     # только быстрые acceptance (< 1s)
composer test:accept-slow     # только медленные acceptance
composer test:arch            # архитектурные правила (dump/dd проверки)

# Один тест по filter
vendor/bin/phpunit --testsuite=Unit --filter=FooTestCase
tests/runner.php vendor/bin/phpunit --testsuite=Acceptance --filter=BarTest

# Перегенерация fast/slow split (после прогона test:accept)
php tests/phpunit-generate.php

# Скачать бинарники (RR + temporal CLI + temporal-test-server)
composer get:binaries
```

## Главные принципы

- **Тесты сами поднимают и тушат серверы.** Bootstrap'ы регистрируют `register_shutdown_function`'ы; ничего вручную запускать/останавливать не надо.
- **При фейле acceptance-теста RR перезапускается** ([tests/Acceptance/App/TestCase.php:117-122](../../tests/Acceptance/App/TestCase.php:117)). Следующий тест получает чистого воркера. Каскадных фейлов не будет.
- **Functional ≠ Acceptance.** Functional — поверх Java time-skipping test-server'а (быстрые long-timer тесты). Acceptance — поверх реального `temporal start-dev` (нужны server-side фичи без time-skip'а).
- **Harness не трогаем.** Эти тесты приходят от Temporal-команды для cross-SDK совместимости. Наши тесты — в `Acceptance/Extra/`.
- **Fast/slow split — генерируется**, не задаётся вручную. После прогона `test:accept` запускаешь `phpunit-generate.php`, и `phpunit.xml.dist` обновляется.

## См. также

- [Архитектура runtime'а](../runtime/architecture.md) — как RR работает с PHP в general.
- [Wire-протокол PHP↔RR](../runtime/worker-rr-protocol.md) — что передаётся между PHP и RR.
- Корневой [tests/README.md](../../tests/README.md) — оригинальные комментарии к структуре тестов.
