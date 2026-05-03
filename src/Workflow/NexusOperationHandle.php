<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;

/**
 * Handle to an in-flight Nexus operation started from a workflow.
 *
 * The handle is fully populated by the time it is returned: by the moment a
 * caller has it, the start response has already arrived and the discriminator
 * is known.
 *
 *  - {@see self::$operationToken} — server-issued token (`string` for async
 *    operations, `null` for sync operations which complete inline).
 *  - {@see self::getResult()} — typed value promise. For sync ops it is
 *    already-resolved by the time the handle exists; for async ops it
 *    resolves once polling picks up the eventual result.
 *
 * ```php
 * $handle = yield $stub->start('order.place', [$order]);
 * $token  = $handle->operationToken;          // string|null, no race
 * $result = yield $handle->getResult();
 * ```
 *
 * @template T
 */
final class NexusOperationHandle
{
    private readonly PromiseInterface $resultPromise;

    /**
     * @param ?string $operationToken Server-issued token; `string` for async
     *        operations, `null` for sync.
     * @param PromiseInterface $rawResult Resolves with a
     *        {@see \Temporal\DataConverter\ValuesInterface} carrying the
     *        wire-level result payloads. Rejects on failure.
     * @param Type|string|\ReflectionClass|\ReflectionType|null $returnType
     *        Type used to decode the result value.
     */
    public function __construct(
        public readonly ?string $operationToken,
        PromiseInterface $rawResult,
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
    ) {
        $this->resultPromise = EncodedValues::decodePromise($rawResult, $returnType);
    }

    /**
     * Promise resolves with the typed result, rejects on failure/cancel.
     * Safe to call multiple times.
     *
     * @return PromiseInterface<T>
     */
    public function getResult(): PromiseInterface
    {
        return $this->resultPromise;
    }

    /**
     * Server-issued operation token. `string` for async operations,
     * `null` for sync (which complete inline and have no token).
     *
     * Backwards-compatible alias for the `operationToken` readonly property.
     */
    public function getOperationToken(): ?string
    {
        return $this->operationToken;
    }
}
