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
use Temporal\Interceptor\Header;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Interceptor\WorkflowClient\SignalWithStartInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Internal\Support\DateInterval;
use Temporal\Workflow\WorkflowExecution;

/**
 * @internal
 */
final class WorkflowStarter
{
    /**
     * @param ServiceClientInterface $serviceClient
     * @param DataConverterInterface $converter
     * @param ClientOptions $clientOptions
     * @param Pipeline<WorkflowClientCallsInterceptor, WorkflowExecution> $interceptors
     */
    public function __construct(
        private ServiceClientInterface $serviceClient,
        private DataConverterInterface $converter,
        private ClientOptions $clientOptions,
        private Pipeline $interceptors,
    ) {
    }

    /**
     * @param string $workflowType
     * @param WorkflowOptions $options
     * @param array $args
     * @param HeaderInterface|null $header
     *
     * @return WorkflowExecution
     *
     * @throws ServiceClientException
     * @throws WorkflowExecutionAlreadyStartedException
     */
    public function start(
        string $workflowType,
        WorkflowOptions $options,
        array $args = [],
    ): WorkflowExecution {
        $header = Header::empty();
        $arguments = EncodedValues::fromValues($args, $this->converter);

        return $this->interceptors->with(
            fn (StartInput $input): WorkflowExecution => $this->executeRequest(
                $this->configureExecutionRequest(new StartWorkflowExecutionRequest(), $input)
            ),
            /** @see WorkflowClientCallsInterceptor::start */
            'start',
        )(new StartInput($options->workflowId, $workflowType, $header, $arguments, $options));
    }

    /**
     * @param string $workflowType
     * @param WorkflowOptions $options
     * @param string $signal
     * @param array $signalArgs
     * @param array $startArgs
     * @param HeaderInterface|null $header
     *
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
        array $startArgs = [],
    ): WorkflowExecution {
        $header = Header::empty();
        $arguments = EncodedValues::fromValues($startArgs, $this->converter);
        $signalArguments = EncodedValues::fromValues($signalArgs, $this->converter);

        return $this->interceptors->with(
            function (SignalWithStartInput $input): WorkflowExecution {
                $request = $this->configureExecutionRequest(
                    new SignalWithStartWorkflowExecutionRequest(),
                    $input->workflowStartInput,
                );

                $request->setSignalName($input->signalName);
                if (!$input->signalArguments->isEmpty()) {
                    $request->setSignalInput($input->signalArguments->toPayloads());
                }


                return $this->executeRequest($request);
            },
            /** @see WorkflowClientCallsInterceptor::signalWithStart */
            'signalWithStart',
        )(
            new SignalWithStartInput(
                new StartInput($options->workflowId, $workflowType, $header, $arguments, $options),
                $signal,
                $signalArguments,
            ),
        );
    }

    /**
     * @param StartWorkflowExecutionRequest|SignalWithStartWorkflowExecutionRequest $request
     *        use {@see configureExecutionRequest()} to prepare request
     *
     * @throws ServiceClientException
     * @throws WorkflowExecutionAlreadyStartedException
     */
    private function executeRequest(StartWorkflowExecutionRequest|SignalWithStartWorkflowExecutionRequest $request,
    ): WorkflowExecution {
        try {
            $response = $request instanceof StartWorkflowExecutionRequest
                ? $this->serviceClient->StartWorkflowExecution($request)
                : $this->serviceClient->SignalWithStartWorkflowExecution($request);
        } catch (ServiceClientException $e) {
            $f = $e->getFailure(WorkflowExecutionAlreadyStartedFailure::class);

            if ($f instanceof WorkflowExecutionAlreadyStartedFailure) {
                $execution = new WorkflowExecution($request->getWorkflowId(), $f->getRunId());

                throw new WorkflowExecutionAlreadyStartedException(
                    $execution,
                    $request->getWorkflowType()->getName(),
                    $e
                );
            }

            throw $e;
        }

        return new WorkflowExecution(
            $request->getWorkflowId(),
            $response->getRunId(),
        );
    }

    /**
     * @template TRequest of StartWorkflowExecutionRequest|SignalWithStartWorkflowExecutionRequest
     *
     * @param TRequest $req
     * @param StartInput $input
     *
     * @return TRequest
     *
     * @throws \Exception
     */
    private function configureExecutionRequest(
        StartWorkflowExecutionRequest|SignalWithStartWorkflowExecutionRequest $req,
        StartInput $input,
    ): StartWorkflowExecutionRequest|SignalWithStartWorkflowExecutionRequest {
        $options = $input->options;
        $header = $input->header;

        \assert($header instanceof Header);
        $header->setDataConverter($this->converter);

        $req->setRequestId(Uuid::v4())
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setTaskQueue(new TaskQueue(['name' => $options->taskQueue]))
            ->setWorkflowType(new WorkflowType(['name' => $input->workflowType]))
            ->setWorkflowId($input->workflowId)
            ->setCronSchedule($options->cronSchedule ?? '')
            ->setRetryPolicy($options->retryOptions ? $options->retryOptions->toWorkflowRetryPolicy() : null)
            ->setWorkflowIdReusePolicy($options->workflowIdReusePolicy)
            ->setWorkflowRunTimeout(DateInterval::toDuration($options->workflowRunTimeout))
            ->setWorkflowExecutionTimeout(DateInterval::toDuration($options->workflowExecutionTimeout))
            ->setWorkflowTaskTimeout(DateInterval::toDuration($options->workflowTaskTimeout))
            ->setMemo($options->toMemo($this->converter))
            ->setSearchAttributes($options->toSearchAttributes($this->converter))
            ->setHeader($header->toHeader());

        $delay = DateInterval::toDuration($options->workflowStartDelay);
        if ($delay !== null && ($delay->getSeconds() > 0 || $delay->getNanos() > 0)) {
            $req->setWorkflowStartDelay($delay);
        }

        if ($req instanceof StartWorkflowExecutionRequest) {
            $req->setRequestEagerExecution($options->eagerStart);
        }

        if (!$input->arguments->isEmpty()) {
            $req->setInput($input->arguments->toPayloads());
        }

        return $req;
    }
}
