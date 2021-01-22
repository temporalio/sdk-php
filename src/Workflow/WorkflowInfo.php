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
use Cron\CronExpression;
use Temporal\Client\ClientOptions;
use Temporal\Common\CronSchedule;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\ArrayType;
use Temporal\Internal\Marshaller\Type\CronType;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Marshaller\Type\ObjectType;
use Temporal\Internal\Support\Cron;
use Temporal\Worker\FactoryInterface;

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
    public string $namespace = ClientOptions::DEFAULT_NAMESPACE;

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
     * @var CronExpression|null
     */
    #[Marshal(name: 'CronSchedule', type: NullableType::class, of: CronType::class)]
    public ?CronExpression $cronSchedule = null;

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
     * @var array|null
     */
    #[Marshal(name: 'SearchAttributes', type: NullableType::class, of: ArrayType::class)]
    public ?array $searchAttributes = null;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var array|null
     */
    #[Marshal(name: 'SearchAttributes', type: NullableType::class, of: ArrayType::class)]
    public ?array $memo = null;

    /**
     * @readonly
     * @psalm-allow-private-mutation
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

    /**
     * @param WorkflowExecution $execution
     * @return WorkflowInfo
     */
    public function withExecution(WorkflowExecution $execution): self
    {
        $this->execution = $execution;

        return $this;
    }

    /**
     * @param WorkflowType $type
     * @return WorkflowInfo
     */
    public function withType(WorkflowType $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @param string $taskQueue
     * @return WorkflowInfo
     */
    public function withTaskQueue(string $taskQueue): self
    {
        $this->taskQueue = $taskQueue;

        return $this;
    }

    /**
     * @param \DateInterval $executionTimeout
     * @return WorkflowInfo
     */
    public function withExecutionTimeout($executionTimeout): self
    {
        $this->executionTimeout = $executionTimeout;

        return $this;
    }

    /**
     * @param \DateInterval $runTimeout
     * @return WorkflowInfo
     */
    public function withRunTimeout($runTimeout): self
    {
        $this->runTimeout = $runTimeout;

        return $this;
    }

    /**
     * @param \DateInterval $taskTimeout
     * @return WorkflowInfo
     */
    public function withTaskTimeout($taskTimeout): self
    {
        $this->taskTimeout = $taskTimeout;

        return $this;
    }

    /**
     * @param string $namespace
     * @return WorkflowInfo
     */
    public function withNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * @param int $attempt
     * @return WorkflowInfo
     */
    public function withAttempt(int $attempt): self
    {
        $this->attempt = $attempt;

        return $this;
    }

    /**
     * @param string|CronExpression|CronSchedule|null $cronSchedule
     * @return $this
     */
    public function withCronSchedule($cronSchedule): self
    {
        $this->cronSchedule = Cron::parseOrNull($cronSchedule);

        return $this;
    }

    /**
     * @param string|null $continuedExecutionRunId
     * @return WorkflowInfo
     * @todo: remove setters
     */
    public function withContinuedExecutionRunId(?string $continuedExecutionRunId): self
    {
        $this->continuedExecutionRunId = $continuedExecutionRunId;

        return $this;
    }

    /**
     * @param string|null $parentNamespace
     * @return WorkflowInfo
     */
    public function withParentNamespace(?string $parentNamespace): self
    {
        $this->parentNamespace = $parentNamespace;

        return $this;
    }

    /**
     * @param WorkflowExecution|null $parentExecution
     * @return WorkflowInfo
     */
    public function withParentExecution(?WorkflowExecution $parentExecution): self
    {
        $this->parentExecution = $parentExecution;

        return $this;
    }

    /**
     * @param array $searchAttributes
     * @return WorkflowInfo
     */
    public function withSearchAttributes(array $searchAttributes): self
    {
        $this->searchAttributes = $searchAttributes;

        return $this;
    }

    /**
     * @param array $memo
     * @return WorkflowInfo
     */
    public function withMemo(array $memo): self
    {
        $this->memo = $memo;

        return $this;
    }

    /**
     * @param string $binaryChecksum
     * @return $this
     */
    public function withBinaryChecksum(string $binaryChecksum): self
    {
        $this->binaryChecksum = $binaryChecksum;

        return $this;
    }
}
