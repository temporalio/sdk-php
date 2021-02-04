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
use Temporal\Internal\Transport\Request\CancelExternalWorkflow;
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
            '',
            $this->execution->getID(),
            $this->execution->getRunID(),
            $name,
            EncodedValues::fromValues($args)
        );

        return $this->request($request);
    }

    /**
     * {@inheritDoc}
     */
    public function cancel(): PromiseInterface
    {
        $request = new CancelExternalWorkflow(
            '',
            $this->execution->getID(),
            $this->execution->getRunID()
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
}
