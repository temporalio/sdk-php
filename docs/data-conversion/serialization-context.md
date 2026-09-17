# Serialization context

Контекст сериализации даёт кастомному `PayloadConverter`'у знать, **в рамках какого workflow/activity** (де)сериализуется payload — чтобы, например, подписать/зашифровать его ключом, производным от `workflowId` (защита от replay). Фича-паритет с Java ([PR#1695](https://github.com/temporalio/sdk-java/pull/1695)) и Go ([#1352](https://github.com/temporalio/sdk-go/issues/1352)); spec — [features#434](https://github.com/temporalio/features/issues/434), тикет — [#587](https://github.com/temporalio/sdk-php/issues/587).

## Контракт

- [`SerializationContext`](../../src/DataConverter/SerializationContext.php) — маркер. Подтипы:
  - [`WorkflowSerializationContext`](../../src/DataConverter/WorkflowSerializationContext.php) — `{namespace, workflowId}`.
  - [`ActivitySerializationContext`](../../src/DataConverter/ActivitySerializationContext.php) — `+ {workflowType?, activityType?, taskQueue?, isLocal}`.
  - [`HasWorkflowSerializationContext`](../../src/DataConverter/HasWorkflowSerializationContext.php) — общий геттер `getNamespace()`/`getWorkflowId()` (workflowId может быть `null` для standalone-activity).
- [`SerializationContextAwareInterface::withSerializationContext()`](../../src/DataConverter/SerializationContextAwareInterface.php) — converter opt-in. `DataConverter` реализует и переоборачивает context-aware sub-converter'ы; non-aware остаются как есть.
- [`SerializationContextBinder::bind($converter, $context)`](../../src/DataConverter/SerializationContextBinder.php) — `$context === null` или non-aware converter → возвращает converter без изменений.
- `ValuesInterface`/`EncodedValues`/`EncodedCollection` несут `set/getSerializationContext()`; контекст применяется лениво в `toPayloads()`/`getValue()` через мемоизированный bound-converter (инвалидируется в `setDataConverter`/`setSerializationContext`).

## Где контекст проставляется

Worker-side и client-side, симметрично encode/decode: workflow in/out, side effect, continue-as-new, activity in/out/failure/heartbeat (+ retry-input), child workflow, signal (internal/external/client/with-start), query, update (worker+client+with-start), async-completion client (все overload'ы), `describe()` pending-activity, **schedule action input + memo**, client result/failure/cancel. Wire-корреляция request→response контекста — в [`Internal/Transport/Client.php`](../../src/Internal/Transport/Client.php).

Schedule action: контекст ставится на **запись** ([`ScheduleMapper::toMessage`](../../src/Internal/Mapper/ScheduleMapper.php)) и на **чтение** ([`ScheduleHandle::describe`](../../src/Client/Schedule/ScheduleHandle.php)) — для `input` и `memo`, по `action.workflowId`. `searchAttributes`/`header` остаются plain JSON.

## Сознательные расхождения с Java/Go

- **`SearchAttributes` и `header` никогда не контекстуализируются** — как в Java/Go, это plain JSON. `EncodedCollection` (общий контейнер для memo/SA/header) несёт context-плумбинг, но он **инертен пока контекст явно не задан**; для SA/header его не задают.
- **Workflow-runtime memo (start memo, `upsertMemo`, continue-as-new memo) НЕ контекстуализируется.** Эти memo кодирует RoadRunner (`rrtemporal`, Go), а не PHP, поэтому проставить контекст можно было бы только на decode-стороне — что создало бы asymmetry с записями из `upsertMemo` (закодированы без контекста). Контекстуализируется только **schedule action memo** (полностью PHP, write+read симметричны). Полный memo-паритет требует изменений в roadrunner-temporal.
- **Нет пользовательского `PayloadCodec`** (шифрование/сжатие живёт в RoadRunner, не в PHP) и **нет подключаемого `FailureConverter`** (он `final`/static; контекст доходит до failure-details через `TemporalFailure::setSerializationContext` → self-binding `EncodedValues`). Оба — те же границы, что и у memo.
- **nil-return** у `withSerializationContext` исключён типом `: static` (TypeError вместо Go-паники).

## Известные паритет-гэпы (требуют отдельной работы)

- **Child workflow без явного `workflowId`** — родитель кодирует аргументы child без контекста (id ещё не известен), а worker child'а декодирует с контекстом (реальный execution id) → asymmetry для context-aware converter'а. Java избегает этого, генерируя child workflowId на клиенте через `Workflow.randomUUID()` = `nameUUIDFromBytes(runId + ':' + counter)` — детерминированно, **без history-маркера**. PHP может сделать так же, но это требует **новой replay-safe инфраструктуры детерминированных id** (у PHP `uuid()` — это sideEffect/маркер) и меняет назначение id для ВСЕХ child workflow → отдельное изменение с replay-тестами, не в рамках этого ревью. Обход: задавать явный `withWorkflowId()` для child при использовании context-aware converter'а.
- **Async activity completion** (`ActivityCompletionClient`) — PHP best-effort проставляет частичный контекст (by-id: namespace+workflowId; by-token: namespace), но не знает `activityType`/`taskQueue`, поэтому converter, завязанный на эти поля, не сойдётся с worker-side контекстом. Java по умолчанию контекст null и даёт `withContext(ActivitySerializationContext)` для явной передачи полного контекста. PHP-паритет — добавить `withContext` API (follow-up); текущее поведение корректно для namespace-keyed converter'ов.
- **Start `UserMetadata` (summary/details) и built-in `__temporal_workflow_metadata` query** — Java контекстуализирует; PHP пока нет. Низкий приоритет (SDK-метаданные), и требует симметрии encode+decode, иначе повторяется memo-asymmetry.

## Тесты

- Unit: [`SerializationContextBinderTestCase`](../../tests/Unit/DataConverter/SerializationContextBinderTestCase.php), [`DataConverterSerializationContextTestCase`](../../tests/Unit/DataConverter/DataConverterSerializationContextTestCase.php), [`EncodedValuesSerializationContextTestCase`](../../tests/Unit/DataConverter/EncodedValuesSerializationContextTestCase.php), [`EncodedCollectionSerializationContextTestCase`](../../tests/Unit/DataConverter/EncodedCollectionSerializationContextTestCase.php), [`SerializationContextSigningTestCase`](../../tests/Unit/DataConverter/SerializationContextSigningTestCase.php) (mismatch-fails-decode = Go `SigningMismatchFailsDecode`).
- Acceptance: [`SerializationContextTest`](../../tests/Acceptance/Extra/DataConverter/SerializationContextTest.php) — E2E подпись workflow/activity/child/side-effect/signal/query/update/continue-as-new/heartbeat/failure-details + schedule action input/memo.
