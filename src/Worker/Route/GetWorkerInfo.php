<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Route;

use React\Promise\Deferred;
use Temporal\Client\Worker\WorkerInterface;
use Temporal\Client\Workflow\WorkflowProviderInterface;
use Temporal\Client\Workflow\WorkflowWorkerInterface;

class GetWorkerInfo extends Route
{
    /**
     * @var WorkerInterface
     */
    private WorkerInterface $context;

    /**
     * @var string
     */
    private string $queue;

    /**
     * @var string
     */
    private string $id;

    /**
     * @param WorkflowWorkerInterface $context
     * @param string $id
     * @param string $queue
     */
    public function __construct(WorkflowWorkerInterface $context, string $id, string $queue)
    {
        $this->context = $context;
        $this->id = $id;
        $this->queue = $queue;
    }

    /**
     * @param array $params
     * @param Deferred $resolver
     * @throws \Exception
     */
    public function handle(array $params, Deferred $resolver): void
    {
        $result = [
            'workerId'  => $this->id,
            'taskQueue' => $this->queue,
            'workflows' => $this->workflowsToArray($this->context),
        ];

        $resolver->resolve($result);
    }

    /**
     * @param WorkflowProviderInterface $provider
     * @return array
     */
    private function workflowsToArray(WorkflowProviderInterface $provider): array
    {
        $workflows = [];

        foreach ($provider->getWorkflows() as $workflow) {
            $workflows[] = [
                'name'    => $workflow->getName(),
                'queries' => $this->iterableKeys($workflow->getQueryHandlers()),
                'signals' => $this->iterableKeys($workflow->getSignalHandlers()),
            ];
        }

        if (\count($workflows) === 0) {
            throw new \OutOfRangeException(
                'The worker could not be initialized:' .
                'Worker does not contain any declared workflow'
            );
        }

        return $workflows;
    }

    /**
     * @param iterable|\Traversable|array $iterable
     * @return array|string[]
     */
    private function iterableKeys(iterable $iterable): array
    {
        return \array_keys(\is_array($iterable) ? $iterable : \iterator_to_array($iterable));
    }
}
