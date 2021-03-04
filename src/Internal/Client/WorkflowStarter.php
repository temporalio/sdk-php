<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Client;

use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\Errordetails\V1\WorkflowExecutionAlreadyStartedFailure;
use Temporal\Api\Taskqueue\V1\TaskQueue;
use Temporal\Api\Workflowservice\V1\SignalWithStartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\Uuid;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Client\WorkflowExecutionAlreadyStartedException;
use Temporal\Internal\Support\DateInterval;
use Temporal\Workflow\WorkflowExecution;

/**
 * @internal
 */
final class WorkflowStarter
{
    /**
     * @var ServiceClientInterface
     */
    private ServiceClientInterface $serviceClient;

    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $converter;

    /**
     * @var ClientOptions
     */
    private ClientOptions $clientOptions;

    /**
     * @param ServiceClientInterface $serviceClient
     * @param DataConverterInterface $converter
     * @param ClientOptions $clientOptions
     */
    public function __construct(
        ServiceClientInterface $serviceClient,
        DataConverterInterface $converter,
        ClientOptions $clientOptions
    ) {
        $this->clientOptions = $clientOptions;
        $this->serviceClient = $serviceClient;
        $this->converter = $converter;
    }

    /**
     * @param string $workflowType
     * @param WorkflowOptions $options
     * @param array $args
     * @return WorkflowExecution
     *
     * @throws ServiceClientException
     * @throws WorkflowExecutionAlreadyStartedException
     */
    public function start(
        string $workflowType,
        WorkflowOptions $options,
        array $args = []
    ): WorkflowExecution {
        $workflowId = $options->workflowId ?? Uuid::v4();

        $r = new StartWorkflowExecutionRequest();
        $r
            ->setRequestId(Uuid::v4())
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setTaskQueue(new TaskQueue(['name' => $options->taskQueue]))
            ->setWorkflowType(new WorkflowType(['name' => $workflowType]))
            ->setWorkflowId($workflowId)
            ->setCronSchedule($options->cronSchedule ?? '')
            ->setRetryPolicy($options->retryOptions ? $options->retryOptions->toWorkflowRetryPolicy() : null)
            ->setWorkflowIdReusePolicy($options->workflowIdReusePolicy)
            ->setWorkflowRunTimeout(DateInterval::toDuration($options->workflowRunTimeout))
            ->setWorkflowExecutionTimeout(DateInterval::toDuration($options->workflowExecutionTimeout))
            ->setWorkflowTaskTimeout(DateInterval::toDuration($options->workflowTaskTimeout))
            ->setMemo($options->toMemo($this->converter))
            ->setSearchAttributes($options->toSearchAttributes($this->converter));

        $input = EncodedValues::fromValues($args, $this->converter);
        if (!$input->isEmpty()) {
            $r->setInput($input->toPayloads());
        }

        try {
            $response = $this->serviceClient->StartWorkflowExecution($r);
        } catch (ServiceClientException $e) {
            $f = $e->getFailure(WorkflowExecutionAlreadyStartedFailure::class);

            if ($f instanceof WorkflowExecutionAlreadyStartedFailure) {
                $execution = new WorkflowExecution($r->getWorkflowId(), $f->getRunId());

                throw new WorkflowExecutionAlreadyStartedException(
                    $execution,
                    $workflowType,
                    $e
                );
            }

            throw $e;
        }

        return new WorkflowExecution(
            $workflowId,
            $response->getRunId()
        );
    }

    /**
     * @param string $workflowType
     * @param WorkflowOptions $options
     * @param string $signal
     * @param array $signalArgs
     * @param array $startArgs
     * @return WorkflowExecution
     *
     * @throws ServiceClientException
     * @throws WorkflowExecutionAlreadyStartedException
     */
    public function signalWithStart(
        string $workflowType,
        WorkflowOptions $options,
        string $signal,
        array $signalArgs = [],
        array $startArgs = []
    ): WorkflowExecution {
        $workflowId = $options->workflowId ?? Uuid::v4();

        $r = new SignalWithStartWorkflowExecutionRequest();
        $r
            ->setRequestId(Uuid::v4())
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setTaskQueue(new TaskQueue(['name' => $options->taskQueue]))
            ->setWorkflowType(new WorkflowType(['name' => $workflowType]))
            ->setWorkflowId($workflowId)
            ->setCronSchedule($options->cronSchedule ?? '')
            ->setRetryPolicy($options->retryOptions ? $options->retryOptions->toWorkflowRetryPolicy() : null)
            ->setWorkflowIdReusePolicy($options->workflowIdReusePolicy)
            ->setWorkflowRunTimeout(DateInterval::toDuration($options->workflowRunTimeout))
            ->setWorkflowExecutionTimeout(DateInterval::toDuration($options->workflowExecutionTimeout))
            ->setWorkflowTaskTimeout(DateInterval::toDuration($options->workflowTaskTimeout))
            ->setMemo($options->toMemo($this->converter))
            ->setSearchAttributes($options->toSearchAttributes($this->converter));

        $input = EncodedValues::fromValues($startArgs, $this->converter);
        if (!$input->isEmpty()) {
            $r->setInput($input->toPayloads());
        }

        $r->setSignalName($signal);
        $signalInput = EncodedValues::fromValues($signalArgs, $this->converter);
        if (!$signalInput->isEmpty()) {
            $r->setSignalInput($signalInput->toPayloads());
        }

        try {
            $response = $this->serviceClient->SignalWithStartWorkflowExecution($r);
        } catch (ServiceClientException $e) {
            $f = $e->getFailure(WorkflowExecutionAlreadyStartedFailure::class);

            if ($f instanceof WorkflowExecutionAlreadyStartedFailure) {
                $execution = new WorkflowExecution($r->getWorkflowId(), $f->getRunId());

                throw new WorkflowExecutionAlreadyStartedException(
                    $execution,
                    $workflowType,
                    $e
                );
            }

            throw $e;
        }

        return new WorkflowExecution($workflowId, $response->getRunId());
    }
}
