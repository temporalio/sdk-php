<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal;

use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\OutOfContextException;
use Temporal\Internal\Support\Facade;
use Temporal\Workflow\ActivityStubInterface;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ChildWorkflowStubInterface;
use Temporal\Workflow\ContinueAsNewOptions;
use Temporal\Workflow\ExternalWorkflowStubInterface;
use Temporal\Workflow\ParentClosePolicy;
use Temporal\Workflow\ScopedContextInterface;
use Temporal\Internal\Workflow\WorkflowContext;
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
 *
 * @template-extends Facade<ScopedContextInterface>
 */
final class Workflow extends Facade
{
    public const DEFAULT_VERSION = -1;

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
     * @return \DateTimeInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function now(): \DateTimeInterface
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->now();
    }

    /**
     * Returns {@see false} if not under workflow code.
     *
     * In the case that the workflow is started for the first time,
     * the {@see true} value will be returned.
     *
     * @return bool
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function isReplaying(): bool
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->isReplaying();
    }

    /**
     * Returns information about current workflow execution.
     *
     * @return WorkflowInfo
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function getInfo(): WorkflowInfo
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->getInfo();
    }

    /**
     * Returns workflow execution input arguments.
     *
     * The data is equivalent to what is passed to the workflow handler.
     *
     * For example:
     * <code>
     *  #[WorkflowInterface]
     *  interface ExampleWorkflowInterface
     *  {
     *      #[WorkflowMethod]
     *      public function handle(int $first, string $second);
     *  }
     * </code>
     *
     * And
     *
     * <code>
     *  // ...
     *  $arguments = Workflow::getInput();
     *
     *  // Contains the value passed as the first argument to the workflow
     *  $first = $arguments->getValue(0, Type::TYPE_INT);
     *
     *  // Contains the value passed as the second argument to the workflow
     *  $second = $arguments->getValue(1, Type::TYPE_STRING);
     * </code>
     *
     * @return ValuesInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function getInput(): ValuesInterface
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->getInput();
    }

    /**
     * The method calls an asynchronous task and returns a promise with
     * additional properties/methods.
     *
     * You can use this method to call and manipulate a group of methods.
     *
     * For example:
     *
     * <code>
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
     * </code>
     *
     * You can see more information about the capabilities of the child
     * asynchronous task in {@see CancellationScopeInterface} interface.
     *
     * @param callable $task
     * @return CancellationScopeInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function async(callable $task): CancellationScopeInterface
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->async($task);
    }

    /**
     * The method is similar to the {@see Workflow::async()}, however, unlike
     * it, it creates a child task, the execution of which is not affected by
     * interruption, cancellation or completion of the parent task.
     *
     * Default behaviour through {@see Workflow::async()}:
     *
     * <code>
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
     * </code>
     *
     * When creating a detaching task using {@see Workflow::asyncDetached}
     * inside the parent, it will not be stopped when the parent context
     * finishes working:
     *
     * <code>
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
     * </code>
     *
     * Use asyncDetached to handle cleanup and compensation logic.
     *
     * @param callable $task
     * @return CancellationScopeInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function asyncDetached(callable $task): CancellationScopeInterface
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->asyncDetached($task);
    }

    /**
     * Moves to the next step if the expression evaluates to {@see true}.
     *
     * Please note that a state change should ONLY occur if the internal
     * workflow conditions are met.
     *
     * <code>
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      yield Workflow::await(
     *          Workflow::executeActivity('shouldByContinued')
     *      );
     *
     *      // ...do something
     *  }
     * </code>
     *
     * Or in the case of an explicit signal method execution of the specified
     * workflow.
     *
     * <code>
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
     * </code>
     *
     * @param callable|PromiseInterface ...$conditions
     * @return PromiseInterface
     */
    public static function await(...$conditions): PromiseInterface
    {
        /** @var WorkflowContext $context */
        $context = self::getCurrentContext();

        return $context->await(...$conditions);
    }

    /**
     * Returns {@see true} if any of conditions were fired and {@see false} if
     * timeout was reached.
     *
     * This method is similar to {@see Workflow::await()}, but in any case it
     * will proceed to the next step either if the internal workflow conditions
     * are met, or after the specified timer interval expires.
     *
     * <code>
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      // Continue after 42 seconds or when bool "continued" will be true.
     *      yield Workflow::awaitWithTimeout(42, fn() => $this->continued);
     *
     *      // ...continue execution
     *  }
     * </code>
     *
     * @param DateIntervalValue $interval
     * @param callable|PromiseInterface ...$conditions
     * @return PromiseInterface
     */
    public static function awaitWithTimeout($interval, ...$conditions): PromiseInterface
    {
        /** @var WorkflowContext $context */
        $context = self::getCurrentContext();

        return $context->awaitWithTimeout($interval, ...$conditions);
    }

    /**
     * Returns value of last completion result, if any.
     *
     * @param Type|TypeEnum|mixed $type
     * @return mixed
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function getLastCompletionResult($type = null)
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->getLastCompletionResult($type);
    }

    /**
     * A method that allows you to dynamically register additional query
     * handler in a workflow during the execution of a workflow.
     *
     * <code>
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      Workflow::registerQuery('query', function(string $argument) {
     *          echo sprintf('Executed query "query" with argument "%s"', $argument);
     *      });
     *  }
     * </code>
     *
     * The same method ({@see WorkflowStubInterface::query()}) should be used
     * to call such query handlers as in the case of ordinary query methods.
     *
     * @param string|class-string $queryType
     * @param callable $handler
     * @return ScopedContextInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function registerQuery(string $queryType, callable $handler): ScopedContextInterface
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->registerQuery($queryType, $handler);
    }

    /**
     * The method is similar to the {@see Workflow::registerQuery()}, but it
     * registers an additional signal handler.
     *
     * <code>
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      Workflow::registerSignal('signal', function(string $argument) {
     *          echo sprintf('Executed signal "signal" with argument "%s"', $argument);
     *      });
     *  }
     * </code>
     *
     * The same method ({@see WorkflowStubInterface::signal()}) should be used
     * to call such signal handlers as in the case of ordinary signal methods.
     *
     * @param string $queryType
     * @param callable $handler
     * @return ScopedContextInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function registerSignal(string $queryType, callable $handler): ScopedContextInterface
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->registerSignal($queryType, $handler);
    }

    /**
     * The method is used to update the behavior (code) of an existing workflow
     * which was already implemented earlier in order to get rid of errors of
     * inconsistency of workflow replay and existing new code.
     *
     * <code>
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
     * </code>
     *
     * @param string $changeId
     * @param int $minSupported
     * @param int $maxSupported
     * @return PromiseInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function getVersion(string $changeId, int $minSupported, int $maxSupported): PromiseInterface
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->getVersion($changeId, $minSupported, $maxSupported);
    }

    /**
     * This method serves to isolate any non-pure data. When the workflow is
     * replayed (for example, in case of an error), such isolated data will
     * return the result of the previous replay.
     *
     * <code>
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
     * </code>
     *
     * @param callable $value
     * @return PromiseInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function sideEffect(callable $value): PromiseInterface
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->sideEffect($value);
    }

    /**
     * Stops workflow execution work for a specified period.
     *
     * The first argument can take implementation of the {@see \DateInterval},
     * string Carbon format ({@link https://carbon.nesbot.com/docs/#api-interval})
     * or a positive number, which is equivalent to the seconds for which the
     * workflow should be suspended.
     *
     * <code>
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
     * </code>
     *
     * @param DateIntervalValue $interval
     * @return PromiseInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function timer($interval): PromiseInterface
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->timer($interval);
    }

    /**
     * Method atomically completes the current workflow execution and starts a
     * new execution of the Workflow with the same Workflow Id. The new
     * execution will not carry over any history from the old execution.
     *
     * <code>
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      return yield Workflow::continueAsNew('AnyAnotherWorkflow');
     *  }
     * </code>
     *
     * @param string $type
     * @param array $args
     * @param ContinueAsNewOptions|null $options
     * @return PromiseInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function continueAsNew(
        string $type,
        array $args = [],
        ContinueAsNewOptions $options = null
    ): PromiseInterface {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->continueAsNew($type, $args, $options);
    }

    /**
     * This method is equivalent to {@see Workflow::continueAsNew}, but it takes
     * the workflow class as the first argument, and the further api is built on
     * the basis of calls to the methods of the passed workflow.
     *
     * <code>
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
     * </code>
     *
     * @psalm-template T of object
     *
     * @param class-string<T> $class
     * @param ContinueAsNewOptions|null $options
     * @return T
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function newContinueAsNewStub(string $class, ContinueAsNewOptions $options = null): object
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->newContinueAsNewStub($class, $options);
    }

    /**
     * Method for calling an external workflow without stopping the current one.
     * It is similar to {@see Workflow::continueAsNew}, but does not terminate
     * the current workflow execution.
     *
     * <code>
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      $result = yield Workflow::executeChildWorkflow('AnyAnotherWorkflow');
     *
     *      // Do something else
     *  }
     * </code>
     *
     * Please note that due to the fact that PHP does not allow defining the
     * type on {@see \Generator}, you sometimes need to specify the type of
     * the child workflow result explicitly.
     *
     * <code>
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
     * </code>
     *
     * @param string $type
     * @param array $args
     * @param ChildWorkflowOptions|null $options
     * @param Type|string|\ReflectionType|\ReflectionClass|null $returnType
     * @return PromiseInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function executeChildWorkflow(
        string $type,
        array $args = [],
        ChildWorkflowOptions $options = null,
        $returnType = null
    ): PromiseInterface {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->executeChildWorkflow($type, $args, $options, $returnType);
    }

    /**
     * This method is equivalent to {@see Workflow::executeChildWorkflow}, but
     * it takes the workflow class as the first argument, and the further api
     * is built on the basis of calls to the methods of the passed workflow.
     * For starting abandon child workflow {@see Workflow::newUntypedChildWorkflowStub()}.
     *
     * <code>
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
     * </code>
     *
     * @psalm-template T of object
     *
     * @param class-string<T> $class
     * @param ChildWorkflowOptions|null $options
     * @return T
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function newChildWorkflowStub(string $class, ChildWorkflowOptions $options = null): object
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->newChildWorkflowStub($class, $options);
    }

    /**
     * This method is equivalent to {@see Workflow::newChildWorkflowStub()}, but
     * it takes the workflow name (instead of class name) as the first argument.
     *
     * <code>
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
     * </code>
     *
     * To start abandoned child workflow use `yield` and method `start()`:
     *
     * <code>
     *  #[WorkflowMethod]
     *  public function handler()
     *  {
     *      // ExampleWorkflow proxy
     *      $workflow = Workflow::newUntypedChildWorkflowStub(
     *          'WorkflowName',
     *           ChildWorkflowOptions::new()->withParentClosePolicy(ParentClosePolicy::POLICY_ABANDON)
     *      );
     *
     *      // Start child workflow
     *      yield $workflow->start(42);
     *  }
     * </code>
     *
     * @param string $name
     * @param ChildWorkflowOptions|null $options
     * @return ChildWorkflowStubInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function newUntypedChildWorkflowStub(
        string $name,
        ChildWorkflowOptions $options = null
    ): ChildWorkflowStubInterface {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->newUntypedChildWorkflowStub($name, $options);
    }

    /**
     * This method allows you to create a "proxy" for an existing and
     * running workflow by fqn class name of the existing workflow.
     *
     * <code>
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
     * </code>
     *
     * @psalm-template T of object
     *
     * @param class-string<T> $class
     * @param WorkflowExecution $execution
     * @return T
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function newExternalWorkflowStub(string $class, WorkflowExecution $execution): object
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->newExternalWorkflowStub($class, $execution);
    }

    /**
     * Allows to create a "proxy" for an existing and running workflow by
     * name (type) of the existing workflow.
     *
     * <code>
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
     * </code>
     *
     * @param WorkflowExecution $execution
     * @return ExternalWorkflowStubInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function newUntypedExternalWorkflowStub(WorkflowExecution $execution): ExternalWorkflowStubInterface
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->newUntypedExternalWorkflowStub($execution);
    }

    /**
     * Calls an activity by its name and gets the result of its execution.
     *
     * <code>
     *  #[WorkflowMethod]
     *  public function handler(string $existingWorkflowId)
     *  {
     *      $result1 = yield Workflow::executeActivity('activityName');
     *      $result2 = yield Workflow::executeActivity('anotherActivityName');
     *  }
     * </code>
     *
     * In addition to this method of calling, you can use alternative methods
     * of working with the result using Promise API ({@see PromiseInterface}).
     *
     * <code>
     *  #[WorkflowMethod]
     *  public function handler(string $existingWorkflowId)
     *  {
     *      Workflow::executeActivity('activityName')
     *          ->then(function ($result) {
     *              // Execution result
     *          })
     *          ->otherwise(function (\Throwable $error) {
     *              // Execution error
     *          })
     *      ;
     *  }
     * </code>
     *
     * @param string $type
     * @param array $args
     * @param ActivityOptions|null $options
     * @param \ReflectionType|null $returnType
     * @return PromiseInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function executeActivity(
        string $type,
        array $args = [],
        ActivityOptionsInterface $options = null,
        \ReflectionType $returnType = null
    ): PromiseInterface {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->executeActivity($type, $args, $options, $returnType);
    }

    /**
     * The method returns a proxy over the class containing the activity, which
     * allows you to conveniently and beautifully call all methods within the
     * passed class.
     *
     * <code>
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
     * </code>
     *
     * @psalm-template T of object
     *
     * @param class-string<T> $class
     * @param ActivityOptionsInterface|null $options
     * @return T
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function newActivityStub(string $class, ActivityOptionsInterface $options = null): object
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->newActivityStub($class, $options);
    }

    /**
     * The method creates and returns a proxy class with the specified settings
     * that allows to call an activities with the passed options.
     *
     * <code>
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
     * </code>
     *
     * @param ActivityOptionsInterface|null $options
     * @return ActivityStubInterface
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function newUntypedActivityStub(ActivityOptionsInterface $options = null): ActivityStubInterface
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->newUntypedActivityStub($options);
    }

    /**
     * Returns a complete trace of the last calls (for debugging).
     *
     * @return string
     * @throws OutOfContextException in the absence of the workflow execution context.
     */
    public static function getStackTrace(): string
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();

        return $context->getStackTrace();
    }

    /**
     * Upsert search attributes
     *
     * @param array<string, mixed> $searchAttributes
     */
    public static function upsertSearchAttributes(array $searchAttributes): void
    {
        /** @var ScopedContextInterface $context */
        $context = self::getCurrentContext();
        $context->upsertSearchAttributes($searchAttributes);
    }
}
