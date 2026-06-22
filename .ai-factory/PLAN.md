# Plan — Payload codec layer (Java-parity) alongside converters

Created: 2026-06-21
Mode: fast
Scope: full parity with Java (`PayloadCodec` + `CodecDataConverter` + `encodeFailureAttributes`)

## Settings

- Testing: yes — unit tests + 1 acceptance E2E
- Logging: minimal — codecs run in the hot serialization path; no per-payload chatter. A codec implementation logs only on its own failure path (decode error), routed through an in-scope logger, never `fwrite`. The SDK glue adds no logging.
- Docs: warn-only (no mandatory docs checkpoint), but Task 12 updates `docs/data-conversion/`.
- Roadmap Linkage: none (no `.ai-factory/ROADMAP.md` in repo).

## Background — why

Java/Go/TS expose a **`PayloadCodec`**: a `Payload[] → Payload[]` (byte→byte) transform chained *after* the converter, for cross-cutting concerns (encryption, compression, signing, remote data encoder). PHP has no such layer — users must hand-roll a `DataConverterInterface` decorator or fake a codec via a per-type `PayloadConverterInterface` (see `tests/Acceptance/Harness/DataConverter/CodecTest.php`, whose `Base64PayloadCodec` is actually a converter keyed on `encoding: 'my-encoding'`).

Key architectural finding (from `/aif-explore`): in Java, `CodecDataConverter implements DataConverter` — the codec layer is a **decorator over the converter**, not a transport stage. The same shape drops cleanly into PHP above `EncodedValues` / `FailureConverter` / the worker transport, touching none of them. It composes with the existing serialization context (`SerializationContextAwareInterface` / `SerializationContextBinder`) we shipped earlier.

Reference: `sdk-java/temporal-sdk/src/main/java/io/temporal/common/converter/CodecDataConverter.java`, `payload/codec/{PayloadCodec,ChainCodec}.java`; `sdk-go/converter/codec.go`; `sdk-typescript/packages/common/src/converter/payload-codec.ts`.

## Design decisions

- **Namespace / naming collision.** `Temporal\Worker\Transport\Codec\CodecInterface` already exists (wire-framing of the RR command batch — unrelated). The new user-facing interface is `Temporal\DataConverter\PayloadCodecInterface` (mirrors Java's `io.temporal.payload.codec`).
- **Batch signature, per-payload application.** `PayloadCodecInterface::encode(array): array` takes/returns `list<Payload>` (Java-parity, future-proof). `CodecDataConverter::toPayload`/`fromPayload` apply the chain to a single-element list. Cross-payload codecs are therefore unsupported in MVP — documented as a known limitation.
- **Context-aware via the existing interface.** Codecs MAY implement `SerializationContextAwareInterface`. `ChainCodec` and `CodecDataConverter` rebind context to each aware codec / the inner converter on `withSerializationContext()`. No new binder is introduced; `SerializationContextBinder` stays converter-only, `ChainCodec` does codec rebinding inline.
- **Failure attributes behind an opt-in interface.** `FailureConverter` (static, in `Temporal\Exception\Failure`) must not depend on the concrete `CodecDataConverter`. A new opt-in `EncodedFailureAttributesInterface` carries the wire-shape (`encodedAttributes` payload + `"Encoded failure"` message sentinel + recursive cause). `FailureConverter` checks `instanceof` and delegates. Failure **details** already pass through the converter, so they are codec-encoded automatically; only message/stacktrace need this wrapper, and only when `encodeFailureAttributes: true`.
- **Decorator over `DataConverterInterface`.** `CodecDataConverter` implements `DataConverterInterface` + `SerializationContextAwareInterface`, so every existing construction site keeps working unchanged; config wiring is additive sugar.

## Tasks

### Phase 1 — Core codec abstraction (public API)

- [ ] **Task 1 — `PayloadCodecInterface`.** Create `src/DataConverter/PayloadCodecInterface.php`: `encode(array $payloads): array` and `decode(array $payloads): array`, both `@param list<Payload>` / `@return list<Payload>`. PHPDoc only (no impl). No logging.
- [ ] **Task 2 — `ChainCodec`.** Create `src/DataConverter/ChainCodec.php` implementing `PayloadCodecInterface, SerializationContextAwareInterface`. Constructor: `PayloadCodecInterface ...$codecs`. `encode`: apply codecs **last → first** (earlier codecs wrap later ones). `decode`: **first → last** (reverse). `withSerializationContext`: clone, rebind every codec that is `SerializationContextAwareInterface` (others pass through). Match `ChainCodec.java` ordering exactly. No logging.
- [ ] **Task 3 — `CodecDataConverter`.** Create `src/DataConverter/CodecDataConverter.php` implementing `DataConverterInterface, SerializationContextAwareInterface`. Constructor: `(DataConverterInterface $converter, PayloadCodecInterface ...$codecs)` — wrap codecs in a `ChainCodec` internally; `RawValue` bypasses inner conversion (delegate to inner, which already handles it) but **still** runs through codecs? Match Java: Java runs the codec on whatever the converter produced, including `RawValue`'s payload. Keep parity: `toPayload` = `chain->encode([inner->toPayload($value)])[0]`; `fromPayload` = `inner->fromPayload(chain->decode([$payload])[0], $type)`. `withSerializationContext`: clone, set inner = `SerializationContextBinder::bind($inner, $context)`, chain = `$chain->withSerializationContext($context)`. No logging.

### Phase 2 — Failure-attribute parity (`encodeFailureAttributes`)

- [ ] **Task 4 — `EncodedFailureAttributesInterface` + impl.** Create `src/DataConverter/EncodedFailureAttributesInterface.php` with `encodeFailureAttributes(Failure $failure): Failure` and `decodeFailureAttributes(Failure $failure): Failure`. Implement both on `CodecDataConverter`, gated by a constructor flag `bool $encodeFailureAttributes = false`. Encode: recurse into `getCause()`; wrap `{message, stackTrace}` into a payload via `$this->toPayload(...)` (so it gets codec'd), `setEncodedAttributes(...)`, set message to `"Encoded failure"` sentinel, clear stack trace. Decode: reverse using `hasEncodedAttributes()` / `getEncodedAttributes()` / `clearEncodedAttributes()`. Proto fields confirmed present: `vendor/.../Failure/V1/Failure.php:189`. Mirror `CodecDataConverter.java` `encodeFailure`/`decodeFailure` (attributes only — details already codec'd via the converter). No logging.
- [ ] **Task 5 — Wire `FailureConverter`.** In `src/Exception/Failure/FailureConverter.php`: in `mapExceptionToFailure`, after building `$failure`, `if ($converter instanceof EncodedFailureAttributesInterface) { $failure = $converter->encodeFailureAttributes($failure); }`. In `mapFailureToException`, before `createFailureException`, `if ($converter instanceof EncodedFailureAttributesInterface) { $failure = $converter->decodeFailureAttributes($failure); }`. Use plain `if`-statements (no `and/or`-throw). Preserve existing stack-trace handling order. No logging.

### Phase 3 — First-class config wiring

- [ ] **Task 6 — `WorkerFactory`.** In `src/WorkerFactory.php` add an optional `iterable<PayloadCodecInterface> $codecs = []` to `create()` (and constructor as needed). When non-empty, wrap: `$converter = new CodecDataConverter($converter, ...$codecs)` right after the `$converter ?? DataConverter::createDefault()` at line ~157. Empty → unchanged behaviour. Match existing named-argument style.
- [ ] **Task 7 — `WorkflowClient` + `ScheduleClient`.** Add the same `iterable<PayloadCodecInterface> $codecs = []` parameter and `CodecDataConverter` wrap in `src/Client/WorkflowClient.php` (line ~87) and `src/Client/ScheduleClient.php` (line ~62). Keep `$this->clientOptions->namespace` usages intact (do not touch the serialization-context wiring).
- [ ] **Task 8 — Activity cache + testing factory.** Mirror the wrap in `src/Worker/ActivityInvocationCache/RoadRunnerActivityInvocationCache.php` (line ~30), `InMemoryActivityInvocationCache.php` (line ~26), and `testing/src/WorkerFactory.php` (line ~61). Same `iterable $codecs = []` + `CodecDataConverter` pattern.

### Phase 4 — Acceptance harness support

- [ ] **Task 9 — Harness codec registration.** Add `array $payloadCodecs = []` to `tests/Acceptance/App/Attribute/Client.php`. In `tests/Acceptance/App/Feature/ClientFactory.php`, after building `$converter`, wrap with `CodecDataConverter` when `payloadCodecs` is non-empty (resolve each class via `$this->container->get(...)`). Add worker-side discovery in `tests/Acceptance/App/RuntimeBuilder.php`: detect classes implementing `PayloadCodecInterface` (alongside the existing `PayloadConverterInterface` scan at line 40) and feed them into the worker converter. Keep the existing `payloadConverters` path working.

### Phase 5 — Tests

- [ ] **Task 10 — Unit tests.** Create under `tests/Unit/DataConverter/` (suffix `*TestCase.php`, `#[CoversClass]` per touched class):
  - `ChainCodecTestCase` — encode applies last→first, decode first→last (assert with two order-sensitive fake codecs, e.g. tag-appending), `withSerializationContext` rebinds aware codecs only.
  - `CodecDataConverterTestCase` — round-trip `toPayload`/`fromPayload` through a real byte codec (e.g. gzip via `gzdeflate`/`gzinflate`); context propagation to inner converter + codecs; `RawValue` still passes through codecs (parity assertion).
  - `EncodedFailureAttributesTestCase` — encode wraps message/stacktrace into `encodedAttributes`, sets `"Encoded failure"` sentinel, recurses into cause; decode restores them; flag `false` ⇒ no-op.
  Real behaviour only — no tautologies. No `markTestSkipped`.
- [ ] **Task 11 — Acceptance E2E.** Create `tests/Acceptance/Extra/DataConverter/PayloadCodecTest.php` (suffix `*Test.php`, value-type-scoped DTO to avoid hijacking the shared worker). A real `GzipCodec implements PayloadCodecInterface` (and `SerializationContextAwareInterface` to prove composition). Register via `#[Client(payloadCodecs: [...])]`; capture wire payloads with a `WorkflowClientCallsInterceptor` (mirror `SerializationContextTest`/`CodecTest`). Assert: workflow input + result payloads are gzip-compressed bytes on the wire and decode back to the DTO. Exercise an activity round-trip so worker-side discovery (Task 9) is covered.

### Phase 6 — Docs

- [ ] **Task 12 — Documentation.** Update `docs/data-conversion/` (Russian, matching `artifact_language` of existing docs): add a "Payload codecs" section to `integration.md` (and a Quick-reference line in `README.md`) covering: codec vs converter, the `CodecDataConverter` decorator, config via `WorkerFactory`/`WorkflowClient`/`ScheduleClient` `codecs:`, `encodeFailureAttributes`, the naming-collision note vs `Worker\Transport\Codec`, and known limitations (per-payload application, no cross-payload codecs, remote-data-encoder not included). No code comments added anywhere (hard project rule).

## Commit Plan

- **Commit 1** (Tasks 1–3): `feat(data-converter): add PayloadCodec + ChainCodec + CodecDataConverter`
- **Commit 2** (Tasks 4–5): `feat(data-converter): support encodeFailureAttributes via codec layer`
- **Commit 3** (Tasks 6–8): `feat(worker,client): wire payload codecs into factories`
- **Commit 4** (Task 9): `test(acceptance): payload codec harness registration`
- **Commit 5** (Tasks 10–11): `test: unit + acceptance coverage for payload codecs`
- **Commit 6** (Task 12): `docs(data-conversion): document payload codec layer`

## Known limitations (carried into implementation)

- Per-payload codec application (no cross-payload codecs). Batch interface keeps the door open.
- Remote Data Encoder (external codec server for Temporal Web UI) is **not** included — out of scope.
- `encodeFailureAttributes` covers message + stacktrace; details are codec'd via the converter path.

## Risks

- `RawValue` parity: confirm during Task 3 whether Java runs codecs over `RawValue` payloads (it does) and that this doesn't break the existing `RawValue` "pass-through untouched" contract in PHP. If it conflicts, document the deviation rather than silently diverging.
- Touching `FailureConverter` (Task 5) risks stack-trace assertion churn in `FailureConverterTestCase`; run unit + accept suites after. Note `tests/` is **not** in php-cs-fixer scope — do not run cs-fixer on test files.
- Config-wiring tasks (6–8) add a public parameter to widely-used factories — keep it last-positional / named with a safe `[]` default so no existing call site breaks.
