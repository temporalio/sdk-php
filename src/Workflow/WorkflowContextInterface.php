<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Support\DateInterval;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Workflow;

/**
 * @psalm-import-type DateIntervalValue from DateInterval
 */
interface WorkflowContextInterface extends EnvironmentInterface
{
    /**
     * @see Workflow::getInfo()
     *
     * @return WorkflowInfo
     */
    public function getInfo(): WorkflowInfo;

    /**
     * @see Workflow::getInput()
     *
     * @return ValuesInterface
     */
    public function getInput(): ValuesInterface;

    /**
     * Get value of last completion result, if any.
     *
     * @see Workflow::getLastCompletionResult()
     *
     * @param Type|string|null $type
     * @return mixed
     */
    public function getLastCompletionResult($type = null);

    /**
     * @see Workflow::registerQuery()
     *
     * @param string $queryType
     * @param callable $handler
     * @return $this
     */
    public function registerQuery(string $queryType, callable $handler): self;

    /**
     * @see Workflow::registerSignal()
     *
     * @param string $queryType
     * @param callable $handler
     * @return $this
     */
    public function registerSignal(string $queryType, callable $handler): self;

    /**
     * Exchanges data between worker and host process.
     *
     * @internal This is an internal method
     *
     * @param RequestInterface $request
     * @param bool $cancellable
     * @return PromiseInterface
     */
    public function request(RequestInterface $request, bool $cancellable = true): PromiseInterface;

    /**
     * @see Workflow::getVersion()
     *
     * @param string $changeId
     * @param int $minSupported
     * @param int $maxSupported
     * @return PromiseInterface
     */
    public function getVersion(string $changeId, int $minSupported, int $maxSupported): PromiseInterface;

    /**
     * @see Workflow::sideEffect()
     *
     * @psalm-type SideEffectCallback = callable(): mixed
     * @psalm-param SideEffectCallback $context
     *
     * @param callable $context
     * @return PromiseInterface
     */
    public function sideEffect(callable $context): PromiseInterface;

    /**
     * @internal This is an internal method
     *
     * @param array|null $result
     * @param \Throwable|null $failure
     * @return PromiseInterface
     */
    public function complete(array $result = null, \Throwable $failure = null): PromiseInterface;

    /**
     * @internal This is an internal method
     *
     * @param \Throwable|null $failure
     * @return PromiseInterface
     */
    public function panic(\Throwable $failure = null): PromiseInterface;

    /**
     * @see Workflow::timer()
     *
     * @param DateIntervalValue $interval
     * @return PromiseInterface
     * @see DateInterval
     */
    public function timer($interval): PromiseInterface;

    /**
     * @see Workflow::continueAsNew()
     *
     * @param string $type
     * @param array $args
     * @param ContinueAsNewOptions|null $options
     * @return PromiseInterface
     */
    public function continueAsNew(
        string $type,
        array $args = [],
        ContinueAsNewOptions $options = null
    ): PromiseInterface;

    /**
     * Creates client stub that can be used to continue this workflow as new.
     *
     * @see Workflow::newContinueAsNewStub()
     *
     * @psalm-template T of object
     * @param class-string<T> $class
     * @param ContinueAsNewOptions|null $options
     * @return T
     */
    public function newContinueAsNewStub(string $class, ContinueAsNewOptions $options = null): object;

    /**
     * @see Workflow::executeChildWorkflow()
     *
     * @param string $type
     * @param array $args
     * @param ChildWorkflowOptions|null $options
     * @param Type|string|\ReflectionType|\ReflectionClass|null $returnType
     * @return PromiseInterface
     */
    public function executeChildWorkflow(
        string $type,
        array $args = [],
        ChildWorkflowOptions $options = null,
        $returnType = null
    ): PromiseInterface;

    /**
     * @see Workflow::newChildWorkflowStub()
     *
     * @psalm-template T of object
     * @param class-string<T> $class
     * @param ChildWorkflowOptions|null $options
     * @return T
     */
    public function newChildWorkflowStub(string $class, ChildWorkflowOptions $options = null): object;

    /**
     * @see Workflow::newUntypedChildWorkflowStub()
     *
     * @param string $type
     * @param ChildWorkflowOptions|null $options
     * @return ChildWorkflowStubInterface
     */
    public function newUntypedChildWorkflowStub(
        string $type,
        ChildWorkflowOptions $options = null
    ): ChildWorkflowStubInterface;

    /**
     * Creates client stub that can be used to communicate to an existing
     * workflow execution.
     *
     * @see Workflow::newExternalWorkflowStub()
     *
     * @psalm-template T of object
     * @param class-string<T> $class
     * @param WorkflowExecution $execution
     * @return T
     */
    public function newExternalWorkflowStub(string $class, WorkflowExecution $execution): object;

    /**
     * Creates untyped client stub that can be used to signal or cancel a child
     * workflow.
     *
     * @see Workflow::newUntypedExternalWorkflowStub()
     *
     * @param WorkflowExecution $execution
     * @return ExternalWorkflowStubInterface
     */
    public function newUntypedExternalWorkflowStub(WorkflowExecution $execution): ExternalWorkflowStubInterface;

    /**
     * @see Workflow::executeActivity()
     *
     * @param string $type
     * @param array $args
     * @param ActivityOptions|null $options
     * @param \ReflectionType|null $returnType
     * @return PromiseInterface
     */
    public function executeActivity(
        string $type,
        array $args = [],
        ActivityOptionsInterface $options = null,
        \ReflectionType $returnType = null
    ): PromiseInterface;

    /**
     * @see Workflow::newActivityStub()
     *
     * @psalm-template T of object
     * @param class-string<T> $class
     * @param ActivityOptionsInterface|null $options
     * @return T
     */
    public function newActivityStub(string $class, ActivityOptionsInterface $options = null): object;

    /**
     * @see Workflow::newUntypedActivityStub()
     *
     * @param ActivityOptionsInterface|null $options
     * @return ActivityStubInterface
     */
    public function newUntypedActivityStub(ActivityOptionsInterface $options = null): ActivityStubInterface;

    /**
     * @see Workflow::await()
     *
     * @param callable|PromiseInterface ...$conditions
     * @return PromiseInterface
     */
    public function await(...$conditions): PromiseInterface;

    /**
     * @see Workflow::awaitWithTimeout()
     *
     * Returns {@see true} if any of conditions were fired and {@see false} if
     * timeout was reached.
     *
     * @param DateIntervalValue $interval
     * @param callable|PromiseInterface ...$conditions
     * @return PromiseInterface
     */
    public function awaitWithTimeout($interval, ...$conditions): PromiseInterface;

    /**
     * @see Workflow::getStackTrace()
     *
     * @return string
     */
    public function getStackTrace(): string;

    /**
     * @param array<string, mixed> $searchAttributes
     */
    public function upsertSearchAttributes(array $searchAttributes): void;
}
