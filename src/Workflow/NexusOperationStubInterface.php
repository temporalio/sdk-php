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
use Temporal\DataConverter\Type;

interface NexusOperationStubInterface
{
    /**
     * Options this stub was created with (endpoint, service, timeouts,
     * cancellation type).
     */
    public function getOptions(): NexusOperationOptions;

    /**
     * Sugar over {@see self::start()}->getResult().
     *
     * @param non-empty-string $operation
     * @param array<string, string> $nexusHeaders Raw-string headers carried on the Nexus wire.
     */
    public function execute(
        string $operation,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        array $nexusHeaders = [],
    ): PromiseInterface;

    /**
     * Start a Nexus operation. The returned promise resolves with a
     * {@see NexusOperationHandle} once the start response arrives — by that
     * point the discriminator is known, so the handle's `operationToken` is
     * fully populated (string for async, null for sync) and its result-promise
     * is wired (already-resolved for sync, pending-poll for async).
     *
     * Workflow code yields the returned promise:
     *
     * ```php
     * $handle = yield $stub->start('order.place', [$order]);
     * $token  = $handle->getOperationToken();
     * $result = yield $handle->getResult();
     * ```
     *
     * @param non-empty-string $operation
     * @param array<string, string> $nexusHeaders
     * @return PromiseInterface<NexusOperationHandle>
     */
    public function start(
        string $operation,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        array $nexusHeaders = [],
    ): PromiseInterface;
}
