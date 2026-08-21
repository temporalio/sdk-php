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
     */
    public function execute(
        string $operation,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
    ): PromiseInterface;

    /**
     * Start a Nexus operation. The returned promise resolves with a {@see NexusOperationHandle}
     * once the start response arrives — at that point the discriminator is known, so the handle's
     * operationToken (string for async, null for sync) and result-promise are populated.
     *
     * @param non-empty-string $operation
     * @return PromiseInterface<NexusOperationHandle>
     */
    public function start(
        string $operation,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
    ): PromiseInterface;
}
