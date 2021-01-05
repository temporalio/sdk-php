<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\Payload;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Transport\ClientInterface;
use Temporal\Internal\Transport\Request\ExecuteChildWorkflow;
use Temporal\Internal\Transport\Request\GetChildWorkflowExecution;
use Temporal\Internal\Transport\Request\SignalExternalWorkflow;
use Temporal\Worker\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ChildWorkflowStubInterface;
use Temporal\Workflow\WorkflowExecution;

final class ChildWorkflowStub implements ChildWorkflowStubInterface, ClientInterface
{
    /**
     * @var string
     */
    private string $workflow;

    /**
     * @var ChildWorkflowOptions
     */
    private ChildWorkflowOptions $options;

    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @var Deferred
     */
    private Deferred $execution;

    /**
     * @var ExecuteChildWorkflow|null
     */
    private ?ExecuteChildWorkflow $request = null;

    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $converter;

    /**
     * @param DataConverterInterface $converter
     * @param MarshallerInterface $marshaller
     * @param string $workflow
     * @param ChildWorkflowOptions $options
     */
    public function __construct(
        DataConverterInterface $converter,
        MarshallerInterface $marshaller,
        string $workflow,
        ChildWorkflowOptions $options
    ) {
        $this->converter = $converter;
        $this->marshaller = $marshaller;

        $this->workflow = $workflow;
        $this->options = $options;

        $this->execution = new Deferred();
    }

    /**
     * @return string
     */
    public function getChildWorkflowType(): string
    {
        return $this->workflow;
    }

    /**
     * @return PromiseInterface
     */
    public function getExecution(): PromiseInterface
    {
        return $this->execution->promise();
    }

    /**
     * {@inheritDoc}
     */
    public function execute(array $args = [], \ReflectionType $returnType = null): PromiseInterface
    {
        if ($this->request !== null) {
            throw new \LogicException('Child workflow already has been executed');
        }

        $this->request = new ExecuteChildWorkflow($this->workflow, $args, $this->getOptionsArray());

        $promise = $this->request($this->request);

        $this->request(new GetChildWorkflowExecution($this->request))
            ->then(function (Payload $encoded) {
                $this->execution->resolve(
                    $execution = $this->toExecution($encoded)
                );

                return $execution;
            });

        return Payload::fromPromise($this->converter, $promise, $returnType);
    }

    /**
     * @param Payload $payload
     * @return WorkflowExecution
     * @throws \ReflectionException
     */
    private function toExecution(Payload $payload): WorkflowExecution
    {
        $reflection = new \ReflectionMethod($this, __FUNCTION__);

        return $this->converter->fromPayload($payload, $reflection->getReturnType());
    }

    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        /** @var Workflow\WorkflowContextInterface $context */
        $context = Workflow::getCurrentContext();

        return $context->request($request);
    }

    /**
     * @return array
     */
    private function getOptionsArray(): array
    {
        return $this->marshaller->marshal($this->getOptions());
    }

    /**
     * @return ChildWorkflowOptions
     */
    public function getOptions(): ChildWorkflowOptions
    {
        return $this->options;
    }

    /**
     * {@inheritDoc}
     */
    public function signal(string $name, array $args = []): PromiseInterface
    {
        $execution = $this->execution->promise();

        return $execution->then(function (WorkflowExecution $execution) use ($name, $args) {
            $request = new SignalExternalWorkflow($this->getOptions(), $execution->runId, $name, $args);

            return $this->request($request);
        });
    }
}
