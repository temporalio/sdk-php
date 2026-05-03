<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Client\WorkflowClientInterface;
use Temporal\Nexus\Exception\InvalidArgumentException;

/**
 * Temporal-side context exposed to a Nexus operation handler.
 * Set by NexusTaskHandler around each dispatch, read via {@see Nexus::getOperationContext()}.
 *
 * @since Nexus support
 */
final class NexusOperationContext
{
    /**
     * @param non-empty-string $namespace
     * @param non-empty-string $taskQueue
     * @throws InvalidArgumentException when namespace or taskQueue is empty.
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $taskQueue,
        public readonly WorkflowClientInterface $workflowClient,
    ) {
        /** @psalm-suppress TypeDoesNotContainType — defensive runtime check */
        if ($namespace === '') {
            throw new InvalidArgumentException(
                'NexusOperationContext: namespace must not be empty',
            );
        }
        /** @psalm-suppress TypeDoesNotContainType — defensive runtime check */
        if ($taskQueue === '') {
            throw new InvalidArgumentException(
                'NexusOperationContext: taskQueue must not be empty',
            );
        }
    }
}
