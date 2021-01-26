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
use Temporal\Client\ClientOptions;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Transport\Request\SignalExternalWorkflow;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\ExternalWorkflowStubInterface;
use Temporal\Workflow\WorkflowExecution;

final class ExternalWorkflowStub implements ExternalWorkflowStubInterface
{
    /**
     * @var WorkflowExecution
     */
    private WorkflowExecution $execution;

    /**
     * @param WorkflowExecution $execution
     */
    public function __construct(WorkflowExecution $execution)
    {
        $this->execution = $execution;
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
        $request = new SignalExternalWorkflow(
            // TODO #1 External workflow has no namespace
            // TODO #2 ClientOptions::DEFAULT_NAMESPACE is not a part of server worker options, did u mean "task queue"?
            ClientOptions::DEFAULT_NAMESPACE,
            $this->execution->getID(),
            $this->execution->getRunID(),
            $name,
            EncodedValues::fromValues($args)
        );

        return $this->request($request);
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    protected function request(RequestInterface $request): PromiseInterface
    {
        /** @var Workflow\WorkflowContextInterface $context */
        $context = Workflow::getCurrentContext();

        return $context->request($request);
    }

    /**
     * TODO It is not clear how to cancel a workflow by its identifier?
     */
    public function cancel(): PromiseInterface
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }
}
