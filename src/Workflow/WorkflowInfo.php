<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use Carbon\CarbonInterval;
use Temporal\Client\Worker\FactoryInterface;
use Temporal\Client\Worker\Uuid4;
use Temporal\Client\Workflow\Info\WorkflowExecution;
use Temporal\Client\Workflow\Info\WorkflowType;

final class WorkflowInfo
{
    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var WorkflowExecution
     */
    public WorkflowExecution $execution;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var WorkflowType
     */
    public WorkflowType $type;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    public string $taskQueue = FactoryInterface::DEFAULT_TASK_QUEUE;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateInterval
     */
    public \DateInterval $executionTimeout;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateInterval
     */
    public \DateInterval $runTimeout;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateInterval
     */
    public \DateInterval $taskTimeout;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    public string $namespace = 'default';

    /**
     * Attempt starts from 1 and increased by 1 for every retry
     * if retry policy is specified.
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var int
     */
    public int $attempt = 1;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string|null
     */
    public ?string $cronSchedule = null;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string|null
     */
    public ?string $continuedExecutionRunId = null;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string|null
     */
    public ?string $parentNamespace = null;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var WorkflowExecution|null
     */
    public ?WorkflowExecution $parentExecution = null;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var mixed
     */
    public $searchAttributes;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    public string $binaryChecksum = '';

    /**
     * @param WorkflowExecution $workflowExecution
     * @param WorkflowType $workflowType
     * @throws \Exception
     */
    public function __construct(WorkflowExecution $workflowExecution, WorkflowType $workflowType)
    {
        $this->execution = $workflowExecution;
        $this->type = $workflowType;

        $this->executionTimeout = CarbonInterval::years(10);
        $this->runTimeout = CarbonInterval::years(10);
        $this->taskTimeout = CarbonInterval::years(10);
    }

    /**
     * TODO throw exception in case of incorrect data
     *
     * @param array $data
     * @return WorkflowInfo
     * @throws \Exception
     */
    public static function fromArray(array $data): self
    {
        $instance = new self(
            WorkflowExecution::fromArray($data['WorkflowExecution']),
            WorkflowType::fromArray($data['WorkflowType'])
        );

        $instance->taskQueue = $data['TaskQueueName'];
        $instance->executionTimeout = CarbonInterval::microseconds($data['WorkflowExecutionTimeout']);
        $instance->runTimeout = CarbonInterval::microseconds($data['WorkflowRunTimeout']);
        $instance->taskTimeout = CarbonInterval::microseconds($data['WorkflowTaskTimeout']);
        $instance->attempt = $data['Attempt'];
        $instance->cronSchedule = $data['CronSchedule'] ?: null;
        $instance->continuedExecutionRunId = $data['ContinuedExecutionRunID'] ?: null;
        $instance->searchAttributes = $data['SearchAttributes'];
        $instance->binaryChecksum = $data['BinaryChecksum'];

        if (isset($data['ParentWorkflowExecution'])) {
            $instance->parentNamespace = $data['ParentWorkflowNamespace'] ?: null;
            $instance->parentExecution = WorkflowExecution::fromArray($data['ParentWorkflowExecution']);
        }

        return $instance;
    }
}
