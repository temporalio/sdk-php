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
use Temporal\Common\Priority;
use Temporal\Common\TypedSearchAttributes;
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
    #[Marshal(name: 'WorkflowExecution', type: ObjectType::class, of: WorkflowExecution::class)]
    public WorkflowExecution $execution;

    #[Marshal(name: 'WorkflowType', type: ObjectType::class, of: WorkflowType::class)]
    public WorkflowType $type;

    /**
     * @var non-empty-string
     */
    #[Marshal(name: 'TaskQueueName')]
    public string $taskQueue = WorkerFactoryInterface::DEFAULT_TASK_QUEUE;

    #[Marshal(name: 'WorkflowExecutionTimeout', type: DateIntervalType::class)]
    public \DateInterval $executionTimeout;

    #[Marshal(name: 'WorkflowRunTimeout', type: DateIntervalType::class)]
    public \DateInterval $runTimeout;

    #[Marshal(name: 'WorkflowTaskTimeout', type: DateIntervalType::class)]
    public \DateInterval $taskTimeout;

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
     * This value changes during the lifetime of a Workflow Execution.
     *
     * @var int<0, max>
     * @since SDK 2.6.0
     * @since RoadRunner 2023.2. With lower versions, this field is always 0.
     */
    #[Marshal(name: 'HistoryLength')]
    public int $historyLength = 0;

    /**
     * Size of Workflow history in bytes up until the current moment of execution.
     * This value changes during the lifetime of a Workflow Execution.
     *
     * @var int<0, max>
     * @since SDK 2.11.0
     * @since RoadRunner 2024.2. With lower versions, this field is always 0.
     */
    #[Marshal(name: 'HistorySize')]
    public int $historySize = 0;

    /**
     * Contains true if the server is configured to suggest continue as new and it is suggested.
     * This value changes during the lifetime of a Workflow Execution.
     *
     * @since SDK 2.11.0
     * @since RoadRunner 2024.2. With lower versions, this field is always false.
     */
    #[Marshal(name: 'ShouldContinueAsNew')]
    public bool $shouldContinueAsNew = false;

    /**
     * @see CronSchedule::$interval for more info about cron format.
     */
    #[Marshal(name: 'CronSchedule', type: NullableType::class, of: CronType::class)]
    public ?string $cronSchedule = null;

    #[Marshal(name: 'ContinuedExecutionRunID')]
    public ?string $continuedExecutionRunId = null;

    #[Marshal(name: 'ParentWorkflowNamespace')]
    public ?string $parentNamespace = null;

    #[Marshal(name: 'ParentWorkflowExecution', type: NullableType::class, of: WorkflowExecution::class)]
    public ?WorkflowExecution $parentExecution = null;

    /**
     * @type array<non-empty-string, mixed>
     * @link https://docs.temporal.io/visibility#search-attribute
     */
    #[Marshal(name: 'SearchAttributes', type: NullableType::class, of: ArrayType::class)]
    public ?array $searchAttributes = null;

    /**
     * @since SDK 2.13.0
     * @since RoadRunner 2024.3.2
     * @link https://docs.temporal.io/visibility#search-attribute
     */
    #[Marshal(name: 'TypedSearchAttributes')]
    public TypedSearchAttributes $typedSearchAttributes;

    #[Marshal(name: 'Memo', type: NullableType::class, of: ArrayType::class)]
    public ?array $memo = null;

    #[Marshal(name: 'BinaryChecksum')]
    public string $binaryChecksum = '';

    /**
     * The priority of the Workflow task.
     *
     * @internal ExperimentalAPI
     */
    #[Marshal(name: 'Priority')]
    public Priority $priority;

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
        $this->typedSearchAttributes = TypedSearchAttributes::empty();

        $this->priority = Priority::new();
    }
}
