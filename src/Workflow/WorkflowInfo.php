<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Carbon\CarbonInterval;
use JetBrains\PhpStorm\Immutable;
use Temporal\Client\ClientOptions;
use Temporal\Common\CronSchedule;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\ArrayType;
use Temporal\Internal\Marshaller\Type\CronType;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Marshaller\Type\ObjectType;
use Temporal\Worker\WorkerFactoryInterface;

#[Immutable]
final class WorkflowInfo
{
    /**
     * @var WorkflowExecution
     */
    #[Marshal(name: 'WorkflowExecution', type: ObjectType::class, of: WorkflowExecution::class)]
    public WorkflowExecution $execution;

    /**
     * @var WorkflowType
     */
    #[Marshal(name: 'WorkflowType', type: ObjectType::class, of: WorkflowType::class)]
    public WorkflowType $type;

    /**
     * @var non-empty-string
     */
    #[Marshal(name: 'TaskQueueName')]
    public string $taskQueue = WorkerFactoryInterface::DEFAULT_TASK_QUEUE;

    /**
     * @var \DateInterval
     */
    #[Marshal(name: 'WorkflowExecutionTimeout', type: DateIntervalType::class)]
    public \DateInterval $executionTimeout;

    /**
     * @var \DateInterval
     */
    #[Marshal(name: 'WorkflowRunTimeout', type: DateIntervalType::class)]
    public \DateInterval $runTimeout;

    /**
     * @var \DateInterval
     */
    #[Marshal(name: 'WorkflowTaskTimeout', type: DateIntervalType::class)]
    public \DateInterval $taskTimeout;

    /**
     * @var string
     */
    #[Marshal(name: 'Namespace')]
    public string $namespace = ClientOptions::DEFAULT_NAMESPACE;

    /**
     * Attempt starts from 1 and increased by 1 for every retry
     * if retry policy is specified.
     *
     * @var positive-int
     */
    #[Marshal(name: 'Attempt')]
    public int $attempt = 1;

    /**
     * Contains the count of history events.
     * The counter is automatically incremented in the background.
     *
     * @var int<0, max>
     * @since 2.6.0
     * @since RoadRunner 2023.2. With lower versions, this field is always 0.
     */
    #[Marshal(name: 'HistoryLength')]
    public int $historyLength = 0;

    /**
     * @see CronSchedule::$interval for more info about cron format.
     *
     * @var string|null
     */
    #[Marshal(name: 'CronSchedule', type: NullableType::class, of: CronType::class)]
    public ?string $cronSchedule = null;

    /**
     * @var string|null
     */
    #[Marshal(name: 'ContinuedExecutionRunID')]
    public ?string $continuedExecutionRunId = null;

    /**
     * @var string|null
     */
    #[Marshal(name: 'ParentWorkflowNamespace')]
    public ?string $parentNamespace = null;

    /**
     * @var WorkflowExecution|null
     */
    #[Marshal(name: 'ParentWorkflowExecution', type: NullableType::class, of: WorkflowExecution::class)]
    public ?WorkflowExecution $parentExecution = null;

    /**
     * @var array|null
     */
    #[Marshal(name: 'SearchAttributes', type: NullableType::class, of: ArrayType::class)]
    public ?array $searchAttributes = null;

    /**
     * @var array|null
     */
    #[Marshal(name: 'Memo', type: NullableType::class, of: ArrayType::class)]
    public ?array $memo = null;

    /**
     * @var string
     */
    #[Marshal(name: 'BinaryChecksum')]
    public string $binaryChecksum = '';

    /**
     * WorkflowInfo constructor.
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
