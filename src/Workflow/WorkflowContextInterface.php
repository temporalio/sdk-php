<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Ramsey\Uuid\UuidInterface;
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
     * Returns information about current workflow execution.
     *
     * @see Workflow::getInfo()
     *
     * @return WorkflowInfo
     */
    public function getInfo(): WorkflowInfo;

    /**
     * Returns workflow execution input arguments.
     *
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
     * A method that allows you to dynamically register additional query
     * handler in a workflow during the execution of a workflow.
     *
     * @see Workflow::registerQuery()
     *
     * @param string $queryType
     * @param callable $handler
     * @return $this
     */
    public function registerQuery(string $queryType, callable $handler): self;

    /**
     * Registers a query with an additional signal handler.
     *
     * @see Workflow::registerSignal()
     *
     * @param string $queryType
     * @param callable $handler
     * @return $this
     */
    public function registerSignal(string $queryType, callable $handler): self;

    /**
     * Registers an update method with an optional validator.
     *
     * @see Workflow::registerUpdate()
     *
     * @param non-empty-string $name
     */
    public function registerUpdate(string $name, callable $handler, ?callable $validator): static;

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
     * Updates the behavior of an existing workflow to resolve inconsistency errors during replay.
     *
     * @see Workflow::getVersion()
     *
     * @param string $changeId
     * @param int $minSupported
     * @param int $maxSupported
     * @return PromiseInterface
     */
    public function getVersion(string $changeId, int $minSupported, int $maxSupported): PromiseInterface;

    /**
     * Isolates non-pure data to ensure consistent results during workflow replays.
     *
     * @see Workflow::sideEffect()
     *
     * @template TReturn
     * @param callable(): TReturn $context
     * @return PromiseInterface<TReturn>
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
     * Stops workflow execution work for a specified period.
     *
     * @see Workflow::timer()
     *
     * @param DateIntervalValue $interval
     * @return PromiseInterface
     * @see DateInterval
     */
    public function timer($interval): PromiseInterface;

    /**
     * Completes the current workflow execution atomically and starts a new execution with the same Workflow Id.
     *
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
        ContinueAsNewOptions $options = null,
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
     * Calls an external workflow without stopping the current one.
     *
     * @see Workflow::executeChildWorkflow()
     *
     * @param string $type
     * @param array $args
     * @param ChildWorkflowOptions|null $options
     * @param Type|string|\ReflectionType|\ReflectionClass|null $returnType
     *
     * @return PromiseInterface
     */
    public function executeChildWorkflow(
        string $type,
        array $args = [],
        ChildWorkflowOptions $options = null,
        $returnType = null,
    ): PromiseInterface;

    /**
     * Creates a proxy for a workflow class to execute as a child workflow.
     *
     * @see Workflow::newChildWorkflowStub()
     *
     * @psalm-template T of object
     * @param class-string<T> $class
     * @param ChildWorkflowOptions|null $options
     *
     * @return T
     */
    public function newChildWorkflowStub(
        string $class,
        ChildWorkflowOptions $options = null,
    ): object;

    /**
     * Creates a proxy for a workflow by name to execute as a child workflow.
     *
     * @see Workflow::newUntypedChildWorkflowStub()
     *
     * @param string $type
     * @param ChildWorkflowOptions|null $options
     *
     * @return ChildWorkflowStubInterface
     */
    public function newUntypedChildWorkflowStub(
        string $type,
        ChildWorkflowOptions $options = null,
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
     * Calls an activity by its name and gets the result of its execution.
     *
     * @see Workflow::executeActivity()
     *
     * @param string $type
     * @param array $args
     * @param ActivityOptions|null $options
     * @param Type|string|null|\ReflectionClass|\ReflectionType $returnType
     *
     * @return PromiseInterface<mixed>
     */
    public function executeActivity(
        string $type,
        array $args = [],
        ActivityOptionsInterface $options = null,
        Type|string|\ReflectionClass|\ReflectionType $returnType = null,
    ): PromiseInterface;

    /**
     * The method returns a proxy over the class containing the activity, which
     * allows you to conveniently and beautifully call all methods within the
     * passed class.
     *
     * @see Workflow::newActivityStub()
     *
     * @psalm-template T of object
     * @param class-string<T> $class
     * @param ActivityOptionsInterface|null $options
     *
     * @return T
     */
    public function newActivityStub(
        string $class,
        ActivityOptionsInterface $options = null,
    ): object;

    /**
     * The method creates and returns a proxy class with the specified settings
     * that allows to call an activities with the passed options.
     *
     * @see Workflow::newUntypedActivityStub()
     *
     * @param ActivityOptionsInterface|null $options
     *
     * @return ActivityStubInterface
     */
    public function newUntypedActivityStub(
        ActivityOptionsInterface $options = null,
    ): ActivityStubInterface;

    /**
     * Moves to the next step if the expression evaluates to `true`.
     *
     * @see Workflow::await()
     *
     * @param callable|Mutex|PromiseInterface ...$conditions
     * @return PromiseInterface
     */
    public function await(callable|Mutex|PromiseInterface ...$conditions): PromiseInterface;

    /**
     * Checks if any conditions were met or the timeout was reached.
     *
     * Returns **true** if any of conditions were fired and **false** if
     * timeout was reached.
     *
     * @see Workflow::awaitWithTimeout()
     *
     * @param DateIntervalValue $interval
     * @param callable|Mutex|PromiseInterface ...$conditions
     * @return PromiseInterface<bool>
     */
    public function awaitWithTimeout($interval, callable|Mutex|PromiseInterface ...$conditions): PromiseInterface;

    /**
     * Returns a complete trace of the last calls (for debugging).
     *
     * @see Workflow::getStackTrace()
     *
     * @return string
     */
    public function getStackTrace(): string;

    /**
     * Whether update and signal handlers have finished executing.
     *
     * Consider waiting on this condition before workflow return or continue-as-new, to prevent
     * interruption of in-progress handlers by workflow exit:
     *
     * ```php
     * yield Workflow.await(static fn() => Workflow::allHandlersFinished());
     * ```
     *
     * @return bool True if all handlers have finished executing.
     */
    public function allHandlersFinished(): bool;

    /**
     * @param array<string, mixed> $searchAttributes
     */
    public function upsertSearchAttributes(array $searchAttributes): void;

    /**
     * Generate a UUID.
     *
     * @see Workflow::uuid()
     *
     * @return PromiseInterface<UuidInterface>
     */
    public function uuid(): PromiseInterface;

    /**
     * Generate a UUID version 4 (random).
     *
     * @see Workflow::uuid4()
     *
     * @return PromiseInterface<UuidInterface>
     */
    public function uuid4(): PromiseInterface;

    /**
     * Generate a UUID version 7 (Unix Epoch time).
     *
     * @see Workflow::uuid7()
     *
     * @param \DateTimeInterface|null $dateTime An optional date/time from which
     *     to create the version 7 UUID. If not provided, the UUID is generated
     *     using the current date/time.
     *
     * @return PromiseInterface<UuidInterface>
     */
    public function uuid7(?\DateTimeInterface $dateTime = null): PromiseInterface;
}
