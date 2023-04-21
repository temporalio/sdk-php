<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\WorkflowOutboundCalls\CancelExternalWorkflowInput;
use Temporal\Interceptor\WorkflowOutboundCalls\SignalExternalWorkflowInput;
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Internal\Transport\Request\CancelExternalWorkflow;
use Temporal\Internal\Transport\Request\SignalExternalWorkflow;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\ExternalWorkflowStubInterface;
use Temporal\Workflow\WorkflowExecution;

final class ExternalWorkflowStub implements ExternalWorkflowStubInterface
{
    /**
     * @param WorkflowExecution $execution
     * @param Pipeline<WorkflowOutboundCallsInterceptor, PromiseInterface> $callsInterceptor
     */
    public function __construct(
        private WorkflowExecution $execution,
        private Pipeline $callsInterceptor,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getExecution(): WorkflowExecution
    {
        return $this->execution;
    }

    /**
     * {@inheritDoc}
     */
    public function signal(string $name, array $args = []): PromiseInterface
    {
        return $this->callsInterceptor->with(
            fn(SignalExternalWorkflowInput $input): PromiseInterface => $this
                ->request(
                    new SignalExternalWorkflow(
                        $input->namespace,
                        $input->workflowId,
                        $input->runId,
                        $input->signal,
                        $input->input,
                        $input->childWorkflowOnly,
                    ),
                ),
            /** @see WorkflowOutboundCallsInterceptor::signalExternalWorkflow() */
            'signalExternalWorkflow',
        )(new SignalExternalWorkflowInput(
            '',
            $this->execution->getID(),
            $this->execution->getRunID(),
            $name,
            EncodedValues::fromValues($args),
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function cancel(): PromiseInterface
    {
        return $this->callsInterceptor->with(
            fn(CancelExternalWorkflowInput $input): PromiseInterface => $this
                ->request(new CancelExternalWorkflow($input->namespace, $input->workflowId, $input->runId)),
            /** @see WorkflowOutboundCallsInterceptor::cancelExternalWorkflow() */
            'cancelExternalWorkflow',
        )(new CancelExternalWorkflowInput('', $this->execution->getID(), $this->execution->getRunID()));
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    private function request(RequestInterface $request): PromiseInterface
    {
        // todo intercept
        /** @var Workflow\WorkflowContextInterface $context */
        $context = Workflow::getCurrentContext();

        return $context->request($request);
    }
}
