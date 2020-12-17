<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\Client\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Client\Internal\Repository\RepositoryInterface;
use Temporal\Client\Worker\TaskQueueInterface;

final class InvokeQuery extends WorkflowProcessAwareRoute
{
    /**
     * @var string
     */
    private const ERROR_QUERY_NOT_FOUND = 'unknown queryType %s. KnownQueryTypes=[%s]';

    /**
     * @var TaskQueueInterface
     */
    private TaskQueueInterface $taskQueue;

    /**
     * @param RepositoryInterface $running
     * @param TaskQueueInterface $taskQueue
     */
    public function __construct(RepositoryInterface $running, TaskQueueInterface $taskQueue)
    {
        $this->taskQueue = $taskQueue;

        parent::__construct($running);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        ['runId' => $runId, 'name' => $name] = $payload;

        $instance = $this->findInstanceOrFail($runId);
        $handler = $this->findQueryHandlerOrFail($instance, $name);

        $executor = static fn() => $resolver->resolve($handler($payload['args'] ?? []));
        $this->taskQueue->once(TaskQueueInterface::ON_QUERY, $executor);
    }

    /**
     * @param WorkflowInstanceInterface $instance
     * @param string $name
     * @return \Closure|null
     */
    private function findQueryHandlerOrFail(WorkflowInstanceInterface $instance, string $name): ?\Closure
    {
        $handler = $instance->findQueryHandler($name);

        if ($handler === null) {
            $available = \implode(' ', $instance->getQueryHandlerNames());

            throw new \LogicException(\sprintf(self::ERROR_QUERY_NOT_FOUND, $name, $available));
        }

        return $handler;
    }
}
