<?php

namespace Temporal\Internal\Workflow;

use Carbon\CarbonInterface;
use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Worker\Command\RequestInterface;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\WorkflowContextInterface;
use Temporal\Workflow\WorkflowInfo;

class WorkflowContextScope implements WorkflowContextInterface
{
    /**
     * @var CancellationScopeInterface
     */
    private CancellationScopeInterface $scope;

    /**
     * @var WorkflowContextInterface
     */
    private WorkflowContextInterface $context;

    /**
     * @param CancellationScopeInterface $scope
     * @param WorkflowContextInterface $context
     */
    public function __construct(CancellationScopeInterface $scope, WorkflowContextInterface $context)
    {
        $this->scope = $scope;
        $this->context = $context;
    }

    /**
     * @inheritDoc
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        $this->assertOpen();
        return $this->context->request($request);
    }

    /**
     * @inheritDoc
     */
    public function now(): CarbonInterface
    {
        return $this->context->now();
    }

    /**
     * @inheritDoc
     */
    public function isReplaying(): bool
    {
        return $this->context->isReplaying();
    }

    /**
     * @inheritDoc
     */
    public function getInfo(): WorkflowInfo
    {
        return $this->context->getInfo();
    }

    /**
     * @inheritDoc
     */
    public function getArguments(): array
    {
        return $this->context->getArguments();
    }

    /**
     * @inheritDoc
     */
    public function getDataConverter(): DataConverterInterface
    {
        return $this->context->getDataConverter();
    }

    /**
     * @inheritDoc
     */
    public function registerQuery(string $queryType, callable $handler): WorkflowContextInterface
    {
        return $this->context->registerQuery($queryType, $handler);
    }

    /**
     * @inheritDoc
     */
    public function registerSignal(string $queryType, callable $handler): WorkflowContextInterface
    {
        return $this->context->registerSignal($queryType, $handler);
    }

    /**
     * @inheritDoc
     */
    public function newCancellationScope(callable $handler): CancellationScopeInterface
    {
        return $this->context->newCancellationScope($handler);
    }

    /**
     * @inheritDoc
     */
    public function newDetachedCancellationScope(callable $handler): CancellationScopeInterface
    {
        return $this->context->newDetachedCancellationScope($handler);
    }

    /**
     * @inheritDoc
     */
    public function getVersion(string $changeId, int $minSupported, int $maxSupported): PromiseInterface
    {
        $this->assertOpen();
        return $this->context->getVersion($changeId, $minSupported, $maxSupported);
    }

    /**
     * @inheritDoc
     */
    public function sideEffect(callable $context): PromiseInterface
    {
        $this->assertOpen();
        return $this->context->sideEffect($context);
    }

    /**
     * @inheritDoc
     */
    public function complete($result = null): PromiseInterface
    {
        throw new \LogicException("Unable to complete workflow from inside the scope");
    }

    /**
     * @inheritDoc
     */
    public function continueAsNew(string $name, ...$input): PromiseInterface
    {
        throw new \LogicException("Unable to continue as new workflow from inside the scope");
    }

    /**
     * @inheritDoc
     */
    public function executeChildWorkflow(
        string $type,
        array $args = [],
        ChildWorkflowOptions $options = null,
        \ReflectionType $returnType = null
    ): PromiseInterface {
        $this->assertOpen();
        return $this->context->executeChildWorkflow($type, $args, $options, $returnType);
    }

    /**
     * @inheritDoc
     */
    public function newChildWorkflowStub(string $class, ChildWorkflowOptions $options = null): object
    {
        return $this->context->newChildWorkflowStub($class, $options);
    }

    /**
     * @inheritDoc
     */
    public function executeActivity(
        string $type,
        array $args = [],
        ActivityOptions $options = null,
        \ReflectionType $returnType = null
    ): PromiseInterface {
        $this->assertOpen();
        return $this->context->executeActivity($type, $args, $options, $returnType);
    }

    /**
     * @inheritDoc
     */
    public function newActivityStub(string $class, ActivityOptions $options = null): object
    {
        return $this->context->newActivityStub($class, $options);
    }

    /**
     * @inheritDoc
     */
    public function timer($interval): PromiseInterface
    {
        $this->assertOpen();
        return $this->context->timer($interval);
    }

    /**
     * @inheritDoc
     */
    public function getTrace(): array
    {
        return $this->context->getTrace();
    }

    /**
     * @inheritDoc
     */
    private function assertOpen()
    {
    }
}
