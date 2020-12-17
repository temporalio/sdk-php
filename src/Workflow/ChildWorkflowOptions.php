<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use Temporal\Client\Common\CronSchedule;
use Temporal\Client\Common\RetryOptions;
use Temporal\Client\Common\Uuid;
use Temporal\Client\Exception\FailedCancellationException;
use Temporal\Client\Internal\Marshaller\Meta\Marshal;
use Temporal\Client\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Client\Internal\Marshaller\Type\ObjectType;
use Temporal\Client\Worker\FactoryInterface;

class ChildWorkflowOptions
{
    /**
     * Namespace of the child workflow.
     *
     * Optional: the current workflow (parent)'s namespace will be used if this
     * is not provided.
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    #[Marshal(name: 'Namespace')]
    public string $namespace = 'default';

    /**
     * WorkflowID of the child workflow to be scheduled.
     *
     * Optional: an auto generated workflowID will be used if this is not
     * provided.
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    #[Marshal(name: 'WorkflowID')]
    public string $id;

    /**
     * TaskQueue that the child workflow needs to be scheduled on.
     *
     * Optional: the parent workflow task queue will be used if this is not
     * provided.
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    #[Marshal(name: 'TaskQueue')]
    public string $taskQueue = FactoryInterface::DEFAULT_TASK_QUEUE;

    /**
     * WorkflowExecutionTimeout - The end to end timeout for the child workflow
     * execution including retries and continue as new.
     *
     * Optional: defaults to 10 years
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateInterval
     */
    #[Marshal(name: 'WorkflowExecutionTimeout', type: DateIntervalType::class)]
    public \DateInterval $executionTimeout;

    /**
     * WorkflowRunTimeout - The timeout for a single run of the child workflow
     * execution. Each retry or continue as new should obey this timeout. Use
     * WorkflowExecutionTimeout to specify how long the parent is willing to
     * wait for the child completion.
     *
     * Optional: defaults to WorkflowExecutionTimeout
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateInterval
     */
    #[Marshal(name: 'WorkflowRunTimeout', type: DateIntervalType::class)]
    public \DateInterval $runTimeout;

    /**
     * WorkflowTaskTimeout - The workflow task timeout for the child workflow.
     *
     * Optional: default is 10s if this is not provided (or if 0 is provided).
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var \DateInterval
     */
    #[Marshal(name: 'WorkflowTaskTimeout', type: DateIntervalType::class)]
    public \DateInterval $taskTimeout;

    /**
     * WaitForCancellation - Whether to wait for canceled child workflow to be
     * ended (child workflow can be ended as:
     * completed/failed/timedout/terminated/canceled).
     *
     * Optional: default false
     * @readonly
     * @psalm-allow-private-mutation
     * @var bool
     */
    #[Marshal(name: 'WaitForCancellation')]
    public bool $waitForCancellation = false;

    /**
     * IdReusePolicy - whether server allow reuse of workflow ID,
     * can be useful for dedup logic if set
     * to {@see IdReusePolicy::POLICY_REJECT_DUPLICATE}.
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @psalm-var IdReusePolicy::POLICY_*
     */
    #[Marshal(name: 'WorkflowIDReusePolicy')]
    public int $idReusePolicy = IdReusePolicy::POLICY_UNSPECIFIED;

    /**
     * RetryPolicy specify how to retry child workflow if error happens.
     *
     * Optional: default is no retry.
     *
     * @var RetryOptions
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    #[Marshal(name: 'RetryPolicy', type: ObjectType::class, of: RetryOptions::class)]
    public RetryOptions $retryOptions;

    /**
     * Optional cron schedule for workflow.
     *
     * @see CronSchedule
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    #[Marshal(name: 'CronSchedule')]
    public string $cronSchedule;

    /**
     * Optional non-indexed info that will be shown in list workflow.
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var array
     */
    #[Marshal(name: 'Memo')]
    public array $memo = [];

    /**
     * Optional indexed info that can be used in query of List/Scan/Count
     * workflow APIs (only supported when Temporal server is using
     * ElasticSearch). The key and value type must be registered on Temporal
     * server side.
     *
     * @readonly
     * @psalm-allow-private-mutation
     * @var array
     */
    #[Marshal(name: 'SearchAttributes')]
    public array $searchAttributes = [];

    /**
     * Optional policy to decide what to do for the child.
     *
     * Default is Terminate (if onboarded to this feature).
     *
     * @see ParentClosePolicy
     * @psalm-var ParentClosePolicy::POLICY_*
     * @readonly
     * @psalm-allow-private-mutation
     */
    #[Marshal(name: 'ParentClosePolicy')]
    public int $parentClosePolicy = ParentClosePolicy::POLICY_TERMINATE;

    /**
     * In case of a child workflow cancellation it fails with
     * a {@link FailedCancellationException}. The type defines at which point
     * the exception is thrown.
     *
     * @var int
     */
    #[Marshal(name: 'CancellationType')]
    public int $cancellationType;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->id = Uuid::v4();
    }
}
