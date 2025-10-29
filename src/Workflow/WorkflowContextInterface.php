<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate;
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
     */
    public function getInfo(): WorkflowInfo;

    /**
     * Returns workflow execution input arguments.
     *
     * @see Workflow::getInput()
     */
    public function getInput(): ValuesInterface;

    /**
     * Get value of last completion result, if any.
     *
     * @see Workflow::getLastCompletionResult()
     *
     * @param Type|string|null $type
     */
    public function getLastCompletionResult(mixed $type = null): mixed;

    /**
     * A method that allows you to dynamically register additional query
     * handler in a workflow during the execution of a workflow.
     *
     * @param non-empty-string $queryType
     *
     * @see Workflow::registerQuery()
     *
     * @return $this
     */
    public function registerQuery(string $queryType, callable $handler, string $description): self;

    /**
     * Registers a query with an additional signal handler.
     *
     * @param non-empty-string $queryType
     *
     * @see Workflow::registerSignal()
     *
     * @return $this
     */
    public function registerSignal(string $queryType, callable $handler, string $description): self;

    /**
     * Registers a dynamic Signal handler.
     *
     * @param callable(non-empty-string, ValuesInterface): mixed $handler The handler to call when a Signal is received.
     *        The first parameter is the Signal name, the second is Signal arguments.
     *
     * @return $this
     *
     * @since SDK 2.14.0
     */
    public function registerDynamicSignal(callable $handler): self;

    /**
     * Registers a dynamic Query handler.
     *
     * @param callable(non-empty-string, ValuesInterface): mixed $handler The handler to call when a Query is received.
     *        The first parameter is the Query name, the second is Query arguments.
     *
     * @return $this
     *
     * @since SDK 2.14.0
     */
    public function registerDynamicQuery(callable $handler): self;

    /**
     * Registers a dynamic Update handler.
     *
     * @param callable(non-empty-string, ValuesInterface): mixed $handler The Update handler
     *        The first parameter is the Update name, the second is Update arguments.
     * @param null|callable(non-empty-string, ValuesInterface): mixed $validator The Update validator
     *       The first parameter is the Update name, the second is Update arguments.
     *       It should throw an exception if the validation fails.
     *
     * @return $this
     *
     * @since SDK 2.14.0
     */
    public function registerDynamicUpdate(callable $handler, ?callable $validator = null): self;

    /**
     * Registers an Update method with an optional validator.
     *
     * @see Workflow::registerUpdate()
     *
     * @param non-empty-string $name
     */
    public function registerUpdate(string $name, callable $handler, ?callable $validator, string $description): static;

    /**
     * Exchanges data between worker and host process.
     *
     * @param bool $waitResponse Determine if the Request requires a Response from RoadRunner.
     *
     * @internal This is an internal method
     */
    public function request(
        RequestInterface $request,
        bool $cancellable = true,
        bool $waitResponse = true,
    ): PromiseInterface;

    /**
     * Updates the behavior of an existing workflow to resolve inconsistency errors during replay.
     *
     * @see Workflow::getVersion()
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
     */
    public function complete(?array $result = null, ?\Throwable $failure = null): PromiseInterface;

    /**
     * @internal This is an internal method
     */
    public function panic(?\Throwable $failure = null): PromiseInterface;

    /**
     * Stops workflow execution work for a specified period.
     *
     * @see Workflow::timer()
     *
     * @param DateIntervalValue $interval
     * @see DateInterval
     */
    public function timer($interval, ?TimerOptions $options = null): PromiseInterface;

    /**
     * Completes the current workflow execution atomically and starts a new execution with the same Workflow Id.
     *
     * @see Workflow::continueAsNew()
     */
    public function continueAsNew(
        string $type,
        array $args = [],
        ?ContinueAsNewOptions $options = null,
    ): PromiseInterface;

    /**
     * Creates client stub that can be used to continue this workflow as new.
     *
     * @see Workflow::newContinueAsNewStub()
     *
     * @psalm-template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function newContinueAsNewStub(string $class, ?ContinueAsNewOptions $options = null): object;

    /**
     * Calls an external workflow without stopping the current one.
     *
     * @see Workflow::executeChildWorkflow()
     *
     * @param Type|string|\ReflectionType|\ReflectionClass|null $returnType
     */
    public function executeChildWorkflow(
        string $type,
        array $args = [],
        ?ChildWorkflowOptions $options = null,
        $returnType = null,
    ): PromiseInterface;

    /**
     * Creates a proxy for a workflow class to execute as a child workflow.
     *
     * @see Workflow::newChildWorkflowStub()
     *
     * @psalm-template T of object
     * @param class-string<T> $class
     *
     * @return T
     */
    public function newChildWorkflowStub(
        string $class,
        ?ChildWorkflowOptions $options = null,
    ): object;

    /**
     * Creates a proxy for a workflow by name to execute as a child workflow.
     *
     * @see Workflow::newUntypedChildWorkflowStub()
     */
    public function newUntypedChildWorkflowStub(
        string $type,
        ?ChildWorkflowOptions $options = null,
    ): ChildWorkflowStubInterface;

    /**
     * Creates client stub that can be used to communicate to an existing
     * workflow execution.
     *
     * @see Workflow::newExternalWorkflowStub()
     *
     * @psalm-template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function newExternalWorkflowStub(string $class, WorkflowExecution $execution): object;

    /**
     * Creates untyped client stub that can be used to signal or cancel a child
     * workflow.
     *
     * @see Workflow::newUntypedExternalWorkflowStub()
     */
    public function newUntypedExternalWorkflowStub(WorkflowExecution $execution): ExternalWorkflowStubInterface;

    /**
     * Calls an activity by its name and gets the result of its execution.
     *
     * @see Workflow::executeActivity()
     *
     * @param ActivityOptions|null $options
     *
     * @return PromiseInterface<mixed>
     */
    public function executeActivity(
        string $type,
        array $args = [],
        ?ActivityOptionsInterface $options = null,
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
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
     *
     * @return T
     */
    public function newActivityStub(
        string $class,
        ?ActivityOptionsInterface $options = null,
    ): object;

    /**
     * The method creates and returns a proxy class with the specified settings
     * that allows to call an activities with the passed options.
     *
     * @see Workflow::newUntypedActivityStub()
     */
    public function newUntypedActivityStub(
        ?ActivityOptionsInterface $options = null,
    ): ActivityStubInterface;

    /**
     * Moves to the next step if the expression evaluates to `true`.
     *
     * @see Workflow::await()
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
     * @return PromiseInterface<bool>
     */
    public function awaitWithTimeout($interval, callable|Mutex|PromiseInterface ...$conditions): PromiseInterface;

    /**
     * Returns a complete trace of the last calls (for debugging).
     *
     * @see Workflow::getStackTrace()
     */
    public function getStackTrace(): string;

    /**
     * Whether update and signal handlers have finished executing.
     *
     * Consider waiting on this condition before workflow return or continue-as-new, to prevent
     * interruption of in-progress handlers by workflow exit:
     *
     * ```php
     *  yield Workflow.await(static fn() => Workflow::allHandlersFinished());
     * ```
     *
     * @return bool True if all handlers have finished executing.
     */
    public function allHandlersFinished(): bool;

    /**
     * Updates this Workflow's Memos by merging the provided memo with existing Memos.
     *
     * New Memo is merged by replacing properties of the same name at the first level only.
     * Setting a property to {@see null} clears that key from the Memo.
     *
     * For example:
     *
     * ```php
     *  Workflow::upsertMemo([
     *      'key1' => 'value',
     *      'key3' => ['subkey1' => 'value']
     *      'key4' => 'value',
     *  });
     *
     *  Workflow::upsertMemo([
     *      'key2' => 'value',
     *      'key3' => ['subkey2' => 'value']
     *      'key4' => null,
     *  ]);
     * ```
     *
     * would result in the Workflow having these Memo:
     *
     * ```php
     *  [
     *      'key1' => 'value',
     *      'key2' => 'value',
     *      'key3' => ['subkey2' => 'value'], // Note this object was completely replaced
     *      // Note that 'key4' was completely removed
     *  ]
     * ```
     *
     * @param array<non-empty-string, mixed> $values
     *
     * @since SDK 2.13.0
     * @since RoadRunner 2024.3.3
     * @link https://docs.temporal.io/glossary#memo
     */
    public function upsertMemo(array $values): void;

    /**
     * Upsert search attributes
     *
     * @param array<non-empty-string, mixed> $searchAttributes
     */
    public function upsertSearchAttributes(array $searchAttributes): void;

    /**
     * Upsert typed Search Attributes
     *
     * ```php
     *  Workflow::upsertTypedSearchAttributes(
     *      SearchAttributeKey::forKeyword('CustomKeyword')->valueSet('CustomValue'),
     *      SearchAttributeKey::forInt('MyCounter')->valueSet(42),
     *  );
     * ```
     *
     * @since SDK 2.13.0
     * @since RoadRunner 2024.3.2
     * @link https://docs.temporal.io/visibility#search-attribute
     */
    public function upsertTypedSearchAttributes(SearchAttributeUpdate ...$updates): void;

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

    /**
     * Get logger to use inside the Workflow.
     *
     * Logs in replay mode are omitted unless {@see WorkerOptions::$enableLoggingInReplay} is set to true.
     */
    public function getLogger(): LoggerInterface;

    /**
     * Get the currently running Workflow instance.
     */
    public function getInstance(): object;
}
