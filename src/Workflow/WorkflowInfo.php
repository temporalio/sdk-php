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
use Spiral\Attributes\AttributeReader;
use Temporal\Client\Internal\Marshaller\Marshaller;
use Temporal\Client\Internal\Marshaller\Meta\Marshal;
use Temporal\Client\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Client\Internal\Marshaller\Type\NullableType;
use Temporal\Client\Internal\Marshaller\Type\ObjectType;
use Temporal\Client\Worker\FactoryInterface;
use Temporal\Client\Workflow\Info\WorkflowExecution;
use Temporal\Client\Workflow\Info\WorkflowType;

/**
 * TODO Previous execution result
 */
final class WorkflowInfo
{
    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var WorkflowExecution
     */
    #[Marshal(name: 'WorkflowExecution', type: ObjectType::class, of: WorkflowExecution::class)]
    public WorkflowExecution $execution;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var WorkflowType
     */
    #[Marshal(name: 'WorkflowType', type: ObjectType::class, of: WorkflowType::class)]
    public WorkflowType $type;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    #[Marshal(name: 'TaskQueueName')]
    public string $taskQueue = FactoryInterface::DEFAULT_TASK_QUEUE;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateInterval
     */
    #[Marshal(name: 'WorkflowExecutionTimeout', type: DateIntervalType::class)]
    public \DateInterval $executionTimeout;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateInterval
     */
    #[Marshal(name: 'WorkflowRunTimeout', type: DateIntervalType::class)]
    public \DateInterval $runTimeout;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateInterval
     */
    #[Marshal(name: 'WorkflowTaskTimeout', type: DateIntervalType::class)]
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
     * @readonly
     * @psalm-allow-private-mutation
     * @var string|null
     */
    #[Marshal(name: 'CronSchedule')]
    public ?string $cronSchedule = null;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string|null
     */
    #[Marshal(name: 'ContinuedExecutionRunID')]
    public ?string $continuedExecutionRunId = null;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string|null
     */
    #[Marshal(name: 'ParentWorkflowNamespace')]
    public ?string $parentNamespace = null;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var WorkflowExecution|null
     */
    #[Marshal(name: 'ParentWorkflowExecution', type: NullableType::class, of: WorkflowExecution::class)]
    public ?WorkflowExecution $parentExecution = null;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var mixed
     */
    #[Marshal(name: 'SearchAttributes')]
    public $searchAttributes;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var mixed
     */
    #[Marshal(name: 'Memo')]
    public $memo;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    #[Marshal(name: 'BinaryChecksum')]
    public string $binaryChecksum = '';

    /**
     * @param WorkflowExecution $workflowExecution
     * @param WorkflowType $workflowType
     * @throws \Exception
     */
    public function __construct()
    {
        $this->execution = new WorkflowExecution();
        $this->type = new WorkflowType();

        $this->executionTimeout = CarbonInterval::years(10);
        $this->runTimeout = CarbonInterval::years(10);
        $this->taskTimeout = CarbonInterval::years(10);
    }
}
