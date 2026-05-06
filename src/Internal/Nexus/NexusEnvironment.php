<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Temporal\Client\WorkflowClientInterface;
use Temporal\Nexus\Exception\InvalidArgumentException;

/**
 * @internal
 */
final class NexusEnvironment
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
                'NexusEnvironment: namespace must not be empty',
            );
        }
        /** @psalm-suppress TypeDoesNotContainType — defensive runtime check */
        if ($taskQueue === '') {
            throw new InvalidArgumentException(
                'NexusEnvironment: taskQueue must not be empty',
            );
        }
    }
}
