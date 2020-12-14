<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Client;

use Carbon\CarbonInterval;
use Temporal\Client\Internal\Marshaller\Meta\Marshal;
use Temporal\Client\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Client\Worker\FactoryInterface;

final class WorkflowOptions
{
    /**
     * TODO rename "taskQueue" to "TaskQueueName"
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    #[Marshal(name: 'taskQueue')]
    public string $taskQueue = FactoryInterface::DEFAULT_TASK_QUEUE;

    /**
     * TODO rename "workflowExecutionTimeout" to "WorkflowExecutionTimeout"
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateInterval
     */
    #[Marshal(name: 'workflowExecutionTimeout', type: DateIntervalType::class)]
    public \DateInterval $executionTimeout;

    /**
     * TODO rename "workflowRunTimeout" to "WorkflowRunTimeout"
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateInterval
     */
    #[Marshal(name: 'workflowRunTimeout', type: DateIntervalType::class)]
    public \DateInterval $runTimeout;

    /**
     * TODO rename "workflowTaskTimeout" to "WorkflowTaskTimeout"
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateInterval
     */
    #[Marshal(name: 'workflowTaskTimeout', type: DateIntervalType::class)]
    public \DateInterval $taskTimeout;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    #[Marshal(name: 'Namespace')]
    public string $namespace = 'default';

    /**
     * Attempt starts from 1 and increased by 1 for every retry
     * if retry policy is specified.
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var positive-int
     */
    #[Marshal(name: 'Attempt')]
    public int $attempt = 1;

    /**
     * WorkflowOptions constructor.
     */
    public function __construct()
    {
        $this->executionTimeout = CarbonInterval::years(10);
        $this->runTimeout = CarbonInterval::years(10);
        $this->taskTimeout = CarbonInterval::years(10);
    }
}
