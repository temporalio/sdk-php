<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;

/**
 * Handle to an in-flight Nexus operation started from a workflow. Fully populated
 * on creation — the start response has already arrived, so {@see self::getOperationToken()}
 * is final (server-issued `string` for async operations, `null` for sync) and
 * {@see self::getResult()} is wired (already-resolved for sync, pending for async).
 *
 * @template T
 */
final class NexusOperationHandle
{
    private readonly PromiseInterface $resultPromise;

    /**
     * @param PromiseInterface $rawResult Resolves with a {@see \Temporal\DataConverter\ValuesInterface}
     *        carrying the wire-level result payloads; rejects on failure.
     */
    public function __construct(
        private readonly ?string $operationToken,
        PromiseInterface $rawResult,
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
    ) {
        $this->resultPromise = EncodedValues::decodePromise($rawResult, $returnType);
    }

    /**
     * @return PromiseInterface<T>
     */
    public function getResult(): PromiseInterface
    {
        return $this->resultPromise;
    }

    public function getOperationToken(): ?string
    {
        return $this->operationToken;
    }
}
