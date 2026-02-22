<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal;

use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\OutOfContextException;
use Temporal\Internal\Support\Facade;
use Temporal\Workflow\ActivityStubInterface;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ChildWorkflowStubInterface;
use Temporal\Workflow\ContinueAsNewOptions;
use Temporal\Workflow\ExternalWorkflowStubInterface;
use Temporal\Workflow\Mutex;
use Temporal\Workflow\ScopedContextInterface;
use Temporal\Workflow\TimerOptions;
use Temporal\Workflow\UpdateContext;
use Temporal\Workflow\WorkflowContextInterface;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowInfo;
use Temporal\Internal\Support\DateInterval;

/**
 * This class provides coroutine specific access to active WorkflowContext. It is safe to use this Facade inside
 * your helper classes.
 *
 * This is main class you can use in your workflow code.
 *
 * @psalm-import-type TypeEnum from Type
 * @psalm-import-type DateIntervalValue from DateInterval
 * @see DateInterval
 */
final class Workflow extends Facade
{
    public const DEFAULT_VERSION = -1;

    /**
     * Get the current Workflow context.
     * @throws OutOfContextException
     */
    public static function getCurrentContext(): WorkflowContextInterface
    {
        $ctx = parent::getCurrentContext();
        if (!$ctx instanceof WorkflowContextInterface) {
            throw new OutOfContextException(
                'The Workflow facade can be used only inside workflow code.',
            );
        }
        return $ctx;
    }

    /**
     * Returns current datetime.
     *
     * Unlike "real" system time, this method returns the time at which the
     * given workflow task started at a certain point in time.
     *
     * Thus, in the case of an execution error and when the workflow has been
     * restarted ({@see Workflow::isReplaying()}), the result of this method
     * will return exactly the date and time at which this workflow task was
     * first started, which eliminates the problems of side effects.
     *
     * Please, use this method {@see Workflow::now()} instead of:
     *
     *  - {@see time()} function.
     *  - {@see \DateTime} constructor.
     *  - {@see \DateTimeImmutable} constructor.
     *
     * And each other like this.
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function now(): \DateTimeInterface
    {
        return self::getCurrentContext()->now();
    }

    /**
     * Checks if the code is under a workflow.
     *
     * Returns **false** if not under workflow code.
     *
     * In the case that the workflow is started for the first time, the **true** value will be returned.
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function isReplaying(): bool
    {
        return self::getCurrentContext()->isReplaying();
    }

    /**
     * Returns information about current workflow execution.
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function getInfo(): WorkflowInfo
    {
        return self::getCurrentContext()->getInfo();
    }

    /**
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function getUpdateContext(): ?UpdateContext
    {
        return self::getCurrentContext()->getUpdateContext();
    }

    /**
     * Returns workflow execution input arguments.
     *
     * The data is equivalent to what is passed to the workflow handler.
     *
     * For example:
     * ```php
     *  #[WorkflowInterface]
     *  interface ExampleWorkflowInterface
     *  {
     *      #[WorkflowMethod]
     *      public function handle(int $first, string $second);
     *  }
     * ```
     *
     * And
     *
     * ```php
     *  // ...
     *  $arguments = Workflow::getInput();
     *
     *  // Contains the value passed as the first argument to the workflow
     *  $first = $arguments->getValue(0, Type::TYPE_INT);
     *
     *  // Contains the value passed as the second argument to the workflow
     *  $second = $arguments->getValue(1, Type::TYPE_STRING);
     * ```
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function getInput(): ValuesInterface
    {
        return self::getCurrentContext()->getInput();
    }

    /**
     * The method calls an asynchronous task and returns a promise with
     * additional properties/methods.
     *
     * You can use this method to call and manipulate a group of methods.
     *
     * For example:
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      // Create the new "group" of executions
     *      $promise = Workflow::async(function() {
     *          $first = yield Workflow::executeActivity('first');
     *          $second = yield Workflow::executeActivity('second');
     *
     *          return yield Promise::all([$first, $second]);
     *      });
     *
     *      // Waiting for the execution result
     *      yield $promise;
     *
     *      // Or cancel all group requests (activity executions)
     *      $promise->cancel();
     *
     *      // Or get information about the execution of the group
     *      $promise->isCancelled();
     *  }
     * ```
     *
     * You can see more information about the capabilities of the child
     * asynchronous task in {@see CancellationScopeInterface} interface.
     *
     * @template TReturn
     * @param callable(): (TReturn|\Generator<mixed, mixed, mixed, TReturn>) $task
     * @return CancellationScopeInterface<TReturn>
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function async(callable $task): CancellationScopeInterface
    {
        $ctx = self::getCurrentContext();
        \assert($ctx instanceof ScopedContextInterface);
        return $ctx->async($task);
    }

    /**
     * Creates a child task that is not affected by parent task interruption, cancellation, or completion.
     *
     * The method is similar to the {@see Workflow::async()}, however, unlike
     * it, it creates a child task, the execution of which is not affected by
     * interruption, cancellation or completion of the parent task.
     *
     * Default behaviour through {@see Workflow::async()}:
     *
     * ```php
     *  $parent = Workflow::async(fn() =>
     *      $child = Workflow::async(fn() =>
     *          // ...
     *      )
     *  );
     *
     *  $parent->cancel();
     *
     *  // In this case, the "$child" promise will also be canceled:
     *  $child->isCancelled(); // true
     * ```
     *
     * When creating a detaching task using {@see Workflow::asyncDetached()}
     * inside the parent, it will not be stopped when the parent context
     * finishes working:
     *
     * ```php
     *  $parent = Workflow::async(fn() =>
     *      $child = Workflow::asyncDetached(fn() =>
     *          // ...
     *      )
     *  );
     *
     *  $parent->cancel();
     *
     *  // In this case, the "$child" promise will NOT be canceled:
     *  $child->isCancelled(); // false
     * ```
     *
     * Use asyncDetached to handle cleanup and compensation logic.
     *
     * @template TReturn
     * @param callable(): (TReturn|\Generator<mixed, mixed, mixed, TReturn>) $task
     * @return CancellationScopeInterface<TReturn>
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function asyncDetached(callable $task): CancellationScopeInterface
    {
        $ctx = self::getCurrentContext();
        \assert($ctx instanceof ScopedContextInterface);
        return $ctx->asyncDetached($task);
    }

    /**
     * Moves to the next step if the expression evaluates to `true`.
     *
     * Please note that a state change should ONLY occur if the internal
     * workflow conditions are met.
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      yield Workflow::await(
     *          Workflow::executeActivity('shouldByContinued')
     *      );
     *
     *      // ...do something
     *  }
     * ```
     *
     * Or in the case of an explicit signal method execution of the specified
     * workflow.
     *
     * ```php
     *  private bool $continued = false;
     *
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      yield Workflow::await(fn() => $this->continued);
     *
     *      // ...continue execution
     *  }
     *
     *  #[SignalMethod]
     *  public function continue()
     *  {
     *      $this->continued = true;
     *  }
     * ```
     */
    public static function await(callable|Mutex|PromiseInterface ...$conditions): PromiseInterface
    {
        return self::getCurrentContext()->await(...$conditions);
    }

    /**
     * Checks if any conditions were met or the timeout was reached.
     *
     * Returns **true** if any of conditions were fired and **false** if
     * timeout was reached.
     *
     * This method is similar to {@see Workflow::await()}, but in any case it
     * will proceed to the next step either if the internal workflow conditions
     * are met, or after the specified timer interval expires.
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      // Continue after 42 seconds or when bool "continued" will be true.
     *      yield Workflow::awaitWithTimeout(42, fn() => $this->continued);
     *
     *      // ...continue execution
     *  }
     * ```
     *
     * @param DateIntervalValue $interval
     * @return PromiseInterface<bool>
     */
    public static function awaitWithTimeout($interval, callable|Mutex|PromiseInterface ...$conditions): PromiseInterface
    {
        return self::getCurrentContext()->awaitWithTimeout($interval, ...$conditions);
    }

    /**
     * Returns value of last completion result, if any.
     *
     * @param Type|TypeEnum|mixed $type
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function getLastCompletionResult($type = null): mixed
    {
        return self::getCurrentContext()->getLastCompletionResult($type);
    }

    /**
     * Register a Query handler in the Workflow.
     *
     * ```php
     * Workflow::registerQuery('query', function(string $argument) {
     *     echo sprintf('Executed query "query" with argument "%s"', $argument);
     * });
     * ```
     *
     * The same method ({@see WorkflowStubInterface::query()}) should be used
     * to call such query handlers as in the case of ordinary query methods.
     *
     * @param non-empty-string $queryType Name of the query.
     * @param string $description Description of the query.
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function registerQuery(
        string $queryType,
        callable $handler,
        string $description = '',
    ): ScopedContextInterface {
        $ctx = self::getCurrentContext();
        \assert($ctx instanceof ScopedContextInterface);
        return $ctx->registerQuery($queryType, $handler, $description);
    }

    /**
     * Registers a Signal handler in the Workflow.
     *
     * ```php
     * Workflow::registerSignal('signal', function(string $argument) {
     *     echo sprintf('Executed signal "signal" with argument "%s"', $argument);
     * });
     * ```
     *
     * The same method ({@see WorkflowStubInterface::signal()}) should be used
     * to call such signal handlers as in the case of ordinary signal methods.
     *
     * @param non-empty-string $name
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function registerSignal(string $name, callable $handler, string $description = ''): ScopedContextInterface
    {
        $ctx = self::getCurrentContext();
        \assert($ctx instanceof ScopedContextInterface);
        return $ctx->registerSignal($name, $handler, $description);
    }

    /**
     * Registers a dynamic Signal handler in the Workflow.
     *
     * ```php
     *  Workflow::registerDynamicSignal(function (string $name, ValuesInterface $arguments): void {
     *      Workflow::getLogger()->info(\sprintf(
     *          'Executed signal `%s` with %d arguments',
     *          $name,
     *          $arguments->count(),
     *      ));
     *  });
     * ```
     *
     * @param callable(non-empty-string, ValuesInterface): mixed $handler The handler to call when a Signal is received.
     *        The first parameter is the Signal name, the second is Signal arguments.
     *
     * @since SDK 2.14.0
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function registerDynamicSignal(callable $handler): WorkflowContextInterface
    {
        return self::getCurrentContext()->registerDynamicSignal($handler);
    }

    /**
     * Registers a dynamic Query handler in the Workflow.
     *
     * ```php
     *  Workflow::registerDynamicQuery(function (string $name, ValuesInterface $arguments): string {
     *      return \sprintf(
     *          'Got query `%s` with %d arguments',
     *          $name,
     *          $arguments->count(),
     *      );
     *  });
     * ```
     *
     * @param callable(non-empty-string, ValuesInterface): mixed $handler The handler to call when a Query is received.
     *        The first parameter is the Query name, the second is Query arguments.
     *
     * @since SDK 2.14.0
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function registerDynamicQuery(callable $handler): WorkflowContextInterface
    {
        return self::getCurrentContext()->registerDynamicQuery($handler);
    }

    /**
     * Registers a dynamic Update method in the Workflow.
     *
     * ```php
     *  Workflow::registerDynamicUpdate(
     *      static fn(string $name, ValuesInterface $arguments): string => \sprintf(
     *          'Got update `%s` with %d arguments',
     *          $name,
     *          $arguments->count(),
     *      ),
     *      static fn(string $name, ValuesInterface $arguments) => \str_starts_with(
     *          $name,
     *          'update_',
     *      ) or throw new \InvalidArgumentException('Invalid update name'),
     *  );
     * ```
     *
     * @param callable(non-empty-string, ValuesInterface): mixed $handler The Update handler.
     *        The first parameter is the Update name, the second is Query arguments.
     * @param null|callable(non-empty-string, ValuesInterface): mixed $validator The Update validator.
     *        The first parameter is the Update name, the second is Update arguments.
     *        It should throw an exception if the validation fails.
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     *
     * @since SDK 2.14.0
     */
    public static function registerDynamicUpdate(callable $handler, ?callable $validator = null): WorkflowContextInterface
    {
        return self::getCurrentContext()->registerDynamicUpdate($handler, $validator);
    }

    /**
     * Registers an Update method in the Workflow.
     *
     * ```php
     * Workflow::registerUpdate(
     *     'pushTask',
     *     fn(Task $task) => $this->queue->push($task),
     * );
     * ```
     *
     * Register an Update method with a validator:
     *
     * ```php
     * Workflow::registerUpdate(
     *     'pushTask',
     *     fn(Task $task) => $this->queue->push($task),
     *     fn(Task $task) => $this->isValidTask($task) or throw new \InvalidArgumentException('Invalid task'),
     * );
     * ```
     *
     * @param non-empty-string $name
     * @param callable $handler Handler function to execute the update.
     * @param callable|null $validator Validator function to check the input. It should throw an exception
     *        if the input is invalid.
     *        Note that the validator must have the same parameters as the handler.
     * @throws OutOfContextException in the absence of the workflow execution context.
     * @since SDK 2.11.0
     */
    public static function registerUpdate(
        string $name,
        callable $handler,
        ?callable $validator = null,
        string $description = '',
    ): ScopedContextInterface {
        $ctx = self::getCurrentContext();
        \assert($ctx instanceof ScopedContextInterface);
        return $ctx->registerUpdate($name, $handler, $validator, $description);
    }

    /**
     * Updates the behavior of an existing workflow to resolve inconsistency errors during replay.
     *
     * The method is used to update the behavior (code) of an existing workflow
     * which was already implemented earlier in order to get rid of errors of
     * inconsistency of workflow replay and existing new code.
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      $version = yield Workflow::getVersion('new-activity-added', 1, 2);
     *
     *      $result = yield match($version) {
     *          1 => Workflow::executeActivity('before'),   // Old behaviour
     *          2 => Workflow::executeActivity('after'),    // New behaviour
     *      }
     *  }
     * ```
     *
     * @return PromiseInterface<int>
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function getVersion(string $changeId, int $minSupported, int $maxSupported): PromiseInterface
    {
        return self::getCurrentContext()->getVersion($changeId, $minSupported, $maxSupported);
    }

    /**
     * Isolates non-pure data to ensure consistent results during workflow replays.
     *
     * This method serves to isolate any non-pure data. When the workflow is
     * replayed (for example, in case of an error), such isolated data will
     * return the result of the previous replay.
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      // ❌ Bad: Each call to workflow, the data will change.
     *      $time = hrtime(true);
     *
     *      // ✅ Good: The calculation of the data with the side-effect
     *      //          will be performed once.
     *      $time = yield Workflow::sideEffect(fn() => hrtime(true));
     *  }
     * ```
     *
     * @template TReturn
     * @param callable(): TReturn $value
     * @return PromiseInterface<TReturn>
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function sideEffect(callable $value): PromiseInterface
    {
        return self::getCurrentContext()->sideEffect($value);
    }

    /**
     * Stops workflow execution work for a specified period.
     *
     * The first argument can take implementation of the {@see \DateInterval},
     * string Carbon format ({@link https://carbon.nesbot.com/docs/#api-interval})
     * or a positive number, which is equivalent to the seconds for which the
     * workflow should be suspended.
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      // Wait 10 seconds
     *      yield Workflow::timer(10);
     *
     *      // Wait 42 hours
     *      yield Workflow::timer(new \DateInterval('PT42H'));
     *
     *      // Wait 23 months
     *      yield Workflow::timer('23 months');
     *  }
     * ```
     *
     * @param DateIntervalValue $interval
     * @return PromiseInterface<null>
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function timer($interval, ?TimerOptions $options = null): PromiseInterface
    {
        return self::getCurrentContext()->timer($interval, $options);
    }

    /**
     * Get the current details of the workflow execution.
     */
    public static function getCurrentDetails(): ?string
    {
        return self::getCurrentContext()->getCurrentDetails();
    }

    /**
     * Set the current details of the workflow execution.
     */
    public static function setCurrentDetails(?string $details): void
    {
        self::getCurrentContext()->setCurrentDetails($details);
    }

    /**
     * Completes the current workflow execution atomically and starts a new execution with the same Workflow Id.
     *
     * Method atomically completes the current workflow execution and starts a
     * new execution of the Workflow with the same Workflow Id. The new
     * execution will not carry over any history from the old execution.
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      return yield Workflow::continueAsNew('AnyAnotherWorkflow');
     *  }
     * ```
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function continueAsNew(
        string $type,
        array $args = [],
        ?ContinueAsNewOptions $options = null,
    ): PromiseInterface {
        return self::getCurrentContext()->continueAsNew($type, $args, $options);
    }

    /**
     * Creates a proxy for a workflow class to continue as new.
     *
     * This method is equivalent to {@see Workflow::continueAsNew()}, but it takes
     * the workflow class as the first argument, and the further api is built on
     * the basis of calls to the methods of the passed workflow.
     *
     * ```php
     *  // Any workflow interface example:
     *
     *  #[WorkflowInterface]
     *  interface ExampleWorkflow
     *  {
     *      #[WorkflowMethod]
     *      public function handle(int $value);
     *  }
     *
     *  // Workflow::newContinueAsNewStub usage example:
     *
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      // ExampleWorkflow proxy
     *      $proxy = Workflow::newContinueAsNewStub(ExampleWorkflow::class);
     *
     *      // Executes ExampleWorkflow::handle(int $value)
     *      return yield $proxy->handle(42);
     *  }
     * ```
     *
     * @psalm-template T of object
     *
     * @param class-string<T> $class
     * @return T
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function newContinueAsNewStub(string $class, ?ContinueAsNewOptions $options = null): object
    {
        return self::getCurrentContext()->newContinueAsNewStub($class, $options);
    }

    /**
     * Calls an external workflow without stopping the current one.
     *
     * Method for calling an external workflow without stopping the current one.
     * It is similar to {@see Workflow::continueAsNew()}, but does not terminate
     * the current workflow execution.
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      $result = yield Workflow::executeChildWorkflow('AnyAnotherWorkflow');
     *
     *      // Do something else
     *  }
     * ```
     *
     * Please note that due to the fact that PHP does not allow defining the
     * type on {@see \Generator}, you sometimes need to specify the type of
     * the child workflow result explicitly.
     *
     * ```php
     *  // External child workflow handler method with Generator return type-hint
     *  public function handle(): \Generator
     *  {
     *      yield Workflow::executeActivity('example');
     *
     *      return 42; // Generator which returns int type (Type::TYPE_INT)
     *  }
     *
     *  // Child workflow execution
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      $result = yield Workflow::executeChildWorkflow(
     *          type: 'ChildWorkflow',
     *          returnType: Type::TYPE_INT,
     *      );
     *
     *      // Do something else
     *  }
     * ```
     *
     * @param non-empty-string $type
     * @param list<mixed> $args
     * @param Type|string|\ReflectionType|\ReflectionClass|null $returnType
     * @return PromiseInterface<mixed>
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function executeChildWorkflow(
        string $type,
        array $args = [],
        ?ChildWorkflowOptions $options = null,
        mixed $returnType = null,
    ): PromiseInterface {
        return self::getCurrentContext()->executeChildWorkflow($type, $args, $options, $returnType);
    }

    /**
     * Creates a proxy for a workflow class to execute as a child workflow.
     *
     * This method is equivalent to {@see Workflow::executeChildWorkflow()}, but
     * it takes the workflow class as the first argument, and the further api
     * is built on the basis of calls to the methods of the passed workflow.
     * For starting abandon child workflow {@see Workflow::newUntypedChildWorkflowStub()}.
     *
     * ```php
     *  // Any workflow interface example:
     *
     *  #[WorkflowInterface]
     *  interface ChildWorkflowExample
     *  {
     *      #[WorkflowMethod]
     *      public function handle(int $value);
     *  }
     *
     *  // Workflow::newChildWorkflowStub usage example:
     *
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      // ExampleWorkflow proxy
     *      $proxy = Workflow::newChildWorkflowStub(ChildWorkflowExample::class);
     *
     *      // Executes ChildWorkflowExample::handle(int $value)
     *      $result = yield $proxy->handle(42);
     *
     *      // etc ...
     *  }
     * ```
     *
     * @psalm-template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function newChildWorkflowStub(
        string $class,
        ?ChildWorkflowOptions $options = null,
    ): object {
        return self::getCurrentContext()->newChildWorkflowStub($class, $options);
    }

    /**
     * Creates a proxy for a workflow by name to execute as a child workflow.
     *
     * This method is equivalent to {@see Workflow::newChildWorkflowStub()}, but
     * it takes the workflow name (instead of class name) as the first argument.
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      // ExampleWorkflow proxy
     *      $workflow = Workflow::newUntypedChildWorkflowStub('WorkflowName');
     *
     *      // Executes workflow
     *      $workflow->execute(42);
     *
     *      // Executes workflow signal named "name"
     *      $workflow->signal('name');
     *
     *      // etc ...
     *  }
     * ```
     *
     * To start abandoned child workflow use `yield` and method `start()`:
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      // ExampleWorkflow proxy
     *      $workflow = Workflow::newUntypedChildWorkflowStub(
     *          'WorkflowName',
     *           ChildWorkflowOptions::new()->withParentClosePolicy(ParentClosePolicy::Abandon)
     *      );
     *
     *      // Start child workflow
     *      yield $workflow->start(42);
     *  }
     * ```
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function newUntypedChildWorkflowStub(
        string $name,
        ?ChildWorkflowOptions $options = null,
    ): ChildWorkflowStubInterface {
        return self::getCurrentContext()->newUntypedChildWorkflowStub($name, $options);
    }

    /**
     * This method allows you to create a "proxy" for an existing and
     * running workflow by fqn class name of the existing workflow.
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler(string $existingWorkflowId)
     *  {
     *      $externalWorkflow = Workflow::newExternalWorkflowStub(ClassName::class,
     *          new WorkflowExecution($existingWorkflowId)
     *      );
     *
     *      // The method "signalMethod" from the class "ClassName" will be called:
     *      yield $externalWorkflow->signalMethod();
     *  }
     * ```
     *
     * @psalm-template T of object
     *
     * @param class-string<T> $class
     * @return T
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function newExternalWorkflowStub(string $class, WorkflowExecution $execution): object
    {
        return self::getCurrentContext()->newExternalWorkflowStub($class, $execution);
    }

    /**
     * Allows to create a "proxy" for an existing and running workflow by
     * name (type) of the existing workflow.
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler(string $existingWorkflowId)
     *  {
     *      $externalWorkflow = Workflow::newUntypedExternalWorkflowStub(
     *          new WorkflowExecution($existingWorkflowId)
     *      );
     *
     *      // Executes signal named "name"
     *      $externalWorkflow->signal('name');
     *
     *      // Stops the external workflow
     *      $externalWorkflow->cancel();
     *  }
     * ```
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function newUntypedExternalWorkflowStub(WorkflowExecution $execution): ExternalWorkflowStubInterface
    {
        return self::getCurrentContext()->newUntypedExternalWorkflowStub($execution);
    }

    /**
     * Calls an activity by its name and gets the result of its execution.
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler(string $existingWorkflowId)
     *  {
     *      $result1 = yield Workflow::executeActivity('activityName');
     *      $result2 = yield Workflow::executeActivity('anotherActivityName');
     *  }
     * ```
     *
     * In addition to this method of calling, you can use alternative methods
     * of working with the result using Promise API ({@see PromiseInterface}).
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler(string $existingWorkflowId)
     *  {
     *      Workflow::executeActivity('activityName')
     *          ->then(function ($result) {
     *              // Execution result
     *          })
     *          ->catch(function (\Throwable $error) {
     *              // Execution error
     *          })
     *      ;
     *  }
     * ```
     *
     * @param non-empty-string $type
     * @param ActivityOptions|null $options
     * @return PromiseInterface<mixed>
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function executeActivity(
        string $type,
        array $args = [],
        ?ActivityOptionsInterface $options = null,
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
    ): PromiseInterface {
        return self::getCurrentContext()->executeActivity($type, $args, $options, $returnType);
    }

    /**
     * The method returns a proxy over the class containing the activity, which
     * allows you to conveniently and beautifully call all methods within the
     * passed class.
     *
     * ```php
     *  #[ActivityInterface]
     *  class ExampleActivityClass
     *  {
     *      public function firstActivity() { ... }
     *      public function secondActivity() { ... }
     *      public function thirdActivity() { ... }
     *  }
     *
     *  // Execution
     *  #[WorkflowMethod]
     *  public function handler(string $existingWorkflowId)
     *  {
     *      $activities = Workflow::newActivityStub(ExampleActivityClass::class);
     *
     *      // Activity methods execution
     *      yield $activities->firstActivity();
     *      yield $activities->secondActivity();
     *  }
     * ```
     *
     * @psalm-template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function newActivityStub(
        string $class,
        ?ActivityOptionsInterface $options = null,
    ): object {
        return self::getCurrentContext()->newActivityStub($class, $options);
    }

    /**
     * The method creates and returns a proxy class with the specified settings
     * that allows to call an activities with the passed options.
     *
     * ```php
     *  #[WorkflowMethod]
     *  public function handler(string $existingWorkflowId)
     *  {
     *      $options = ActivityOptions::new()
     *          ->withTaskQueue('custom-task-queue')
     *      ;
     *
     *      $activities = Workflow::newUntypedActivityStub($options);
     *
     *      // Executes an activity named "activity"
     *      $result = yield $activities->execute('activity');
     *  }
     * ```
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function newUntypedActivityStub(
        ?ActivityOptionsInterface $options = null,
    ): ActivityStubInterface {
        return self::getCurrentContext()->newUntypedActivityStub($options);
    }

    /**
     * Returns a complete trace of the last calls (for debugging).
     *
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function getStackTrace(): string
    {
        return self::getCurrentContext()->getStackTrace();
    }

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
    public static function allHandlersFinished(): bool
    {
        $context = self::getCurrentContext();

        return $context->allHandlersFinished();
    }

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
    public static function upsertMemo(array $values): void
    {
        self::getCurrentContext()->upsertMemo($values);
    }

    /**
     * Upsert search attributes
     *
     * @param array<non-empty-string, mixed> $searchAttributes
     */
    public static function upsertSearchAttributes(array $searchAttributes): void
    {
        self::getCurrentContext()->upsertSearchAttributes($searchAttributes);
    }

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
    public static function upsertTypedSearchAttributes(SearchAttributeUpdate ...$updates): void
    {
        self::getCurrentContext()->upsertTypedSearchAttributes(...$updates);
    }

    /**
     * Generate a UUID.
     *
     * @return PromiseInterface<UuidInterface>
     */
    public static function uuid(): PromiseInterface
    {
        $context = self::getCurrentContext();

        return $context->uuid();
    }

    /**
     * Generate a UUID version 4 (random).
     *
     * @return PromiseInterface<UuidInterface>
     */
    public static function uuid4(): PromiseInterface
    {
        $context = self::getCurrentContext();

        return $context->uuid4();
    }

    /**
     * Generate a UUID version 7 (Unix Epoch time).
     *
     * @param \DateTimeInterface|null $dateTime An optional date/time from which
     *        to create the version 7 UUID. If not provided, the UUID is generated
     *        using the current date/time.
     *
     * @return PromiseInterface<UuidInterface>
     */
    public static function uuid7(?\DateTimeInterface $dateTime = null): PromiseInterface
    {
        $context = self::getCurrentContext();

        return $context->uuid7($dateTime);
    }

    /**
     * Run a function when the mutex is released.
     * The mutex is locked for the duration of the function.
     *
     * Note that calling the method creates a non-detached asynchronous context {@see Workflow::async()}.
     * Closing the context using the `cancel()` method will reject the returned promise with a {@see CanceledFailure}.
     *
     * @template T
     * @param Mutex $mutex Mutex name or instance.
     * @param callable(): T $callable Function to run.
     *
     * @return CancellationScopeInterface<T>
     */
    public static function runLocked(Mutex $mutex, callable $callable): CancellationScopeInterface
    {
        return Workflow::async(static function () use ($mutex, $callable): \Generator {
            yield $mutex->lock();

            try {
                return yield $callable();
            } finally {
                $mutex->unlock();
            }
        });
    }

    /**
     * Get logger to use inside the Workflow.
     *
     * Logs in replay mode are omitted unless {@see WorkerOptions::$enableLoggingInReplay} is set to true.
     *
     * ```php
     *  Workflow::getLogger()->notice('Workflow started');
     * ```
     *
     * @since SDK 2.14.0
     */
    public static function getLogger(): LoggerInterface
    {
        return self::getCurrentContext()->getLogger();
    }

    /**
     * Get the currently running Workflow instance.
     */
    public static function getInstance(): object
    {
        return self::getCurrentContext()->getInstance();
    }
}
