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
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\Header;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Transport\Request\ExecuteChildWorkflow;
use Temporal\Internal\Transport\Request\GetChildWorkflowExecution;
use Temporal\Internal\Transport\Request\SignalExternalWorkflow;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ChildWorkflowStubInterface;
use Temporal\Workflow\ParentClosePolicy;
use Temporal\Workflow\WorkflowExecution;

final class ChildWorkflowStub implements ChildWorkflowStubInterface
{
    private Deferred $execution;
    private ?ExecuteChildWorkflow $request = null;
    private ?PromiseInterface $result = null;
    private HeaderInterface $header;

    /**
     * @param MarshallerInterface<array> $marshaller
     */
    public function __construct(
        private readonly MarshallerInterface $marshaller,
        private readonly string $workflow,
        private readonly ChildWorkflowOptions $options,
        HeaderInterface|array $header,
    ) {
        $this->execution = new Deferred();
        $this->header = \is_array($header) ? Header::fromValues($header) : $header;
    }

    public function getChildWorkflowType(): string
    {
        return $this->workflow;
    }

    public function getExecution(): PromiseInterface
    {
        return $this->execution->promise();
    }

    public function start(... $args): PromiseInterface
    {
        if ($this->request !== null) {
            throw new \LogicException('Child workflow already has been executed');
        }

        $this->request = new ExecuteChildWorkflow(
            $this->workflow,
            EncodedValues::fromValues($args),
            $this->getOptionArray(),
            $this->header,
        );

        $cancellable = $this->options->parentClosePolicy !== ParentClosePolicy::Abandon->value;

        $this->result = $this->request($this->request, cancellable: $cancellable);

        $started = $this->request(new GetChildWorkflowExecution($this->request))
            ->then(
                function (ValuesInterface $values): mixed {
                    $execution = $values->getValue(0, WorkflowExecution::class);
                    $this->execution->resolve($execution);

                    return $execution;
                },
            );

        return EncodedValues::decodePromise($started);
    }

    public function getResult($returnType = null): PromiseInterface
    {
        return EncodedValues::decodePromise($this->result, $returnType);
    }

    public function execute(array $args = [], $returnType = null): PromiseInterface
    {
        return $this->start(...$args)->then(fn() => $this->getResult($returnType));
    }

    public function getOptions(): ChildWorkflowOptions
    {
        return $this->options;
    }

    public function signal(string $name, array $args = []): PromiseInterface
    {
        return $this->execution->promise()->then(
            function (WorkflowExecution $execution) use ($name, $args) {
                $request = new SignalExternalWorkflow(
                    $this->getOptions()->namespace,
                    $execution->getID(),
                    null,
                    $name,
                    EncodedValues::fromValues($args),
                    true,
                );

                return $this->request($request);
            },
        );
    }

    protected function request(RequestInterface $request, bool $cancellable = true): PromiseInterface
    {
        /** @var Workflow\WorkflowContextInterface $context */
        $context = Workflow::getCurrentContext();

        return $context->request($request, cancellable: $cancellable);
    }

    private function getOptionArray(): array
    {
        return $this->marshaller->marshal($this->getOptions());
    }
}
