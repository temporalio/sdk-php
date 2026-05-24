<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate;
use Temporal\Common\SideEffectOptions;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ContinueAsNewOptions;
use Temporal\Workflow\Mutex as BaseMutex;
use Temporal\Workflow\ScopedContextInterface;
use Temporal\Workflow\TimerOptions;
use Temporal\Workflow\UpdateContext;
use Temporal\Workflow\WorkflowContextInterface;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowInfo;

/**
 * Fiber-mode drop-in for {@see \Temporal\Workflow}.
 *
 * Migration from Generator mode:
 *
 * 1. Replace `use Temporal\Workflow;` with `use Temporal\Experiments\Fibers\Workflow;`.
 * 2. Delete `yield` in front of every `Workflow::...` call.
 * 3. Drop `\Generator` from workflow / signal / update method return types.
 *
 * Attribute classes ({@see \Temporal\Workflow\WorkflowInterface},
 * {@see \Temporal\Workflow\WorkflowMethod}, {@see \Temporal\Workflow\SignalMethod},
 * {@see \Temporal\Workflow\QueryMethod}, {@see \Temporal\Workflow\UpdateMethod})
 * stay in the standard `Temporal\Workflow\…` namespace.
 *
 * @experimental
 */
final class Workflow
{
    public const DEFAULT_VERSION = \Temporal\Workflow::DEFAULT_VERSION;

    private function __construct() {}

    public static function getCurrentContext(): WorkflowContextInterface
    {
        return \Temporal\Workflow::getCurrentContext();
    }

    public static function now(): \DateTimeInterface
    {
        return \Temporal\Workflow::now();
    }

    public static function isReplaying(): bool
    {
        return \Temporal\Workflow::isReplaying();
    }

    public static function getInfo(): WorkflowInfo
    {
        return \Temporal\Workflow::getInfo();
    }

    public static function getUpdateContext(): ?UpdateContext
    {
        return \Temporal\Workflow::getUpdateContext();
    }

    public static function getInput(): ValuesInterface
    {
        return \Temporal\Workflow::getInput();
    }

    public static function getStackTrace(): string
    {
        return \Temporal\Workflow::getStackTrace();
    }

    public static function allHandlersFinished(): bool
    {
        return \Temporal\Workflow::allHandlersFinished();
    }

    public static function getLogger(): LoggerInterface
    {
        return \Temporal\Workflow::getLogger();
    }

    public static function getInstance(): object
    {
        return \Temporal\Workflow::getInstance();
    }

    public static function getCurrentDetails(): ?string
    {
        return \Temporal\Workflow::getCurrentDetails();
    }

    public static function setCurrentDetails(?string $details): void
    {
        \Temporal\Workflow::setCurrentDetails($details);
    }

    public static function getLastCompletionResult(mixed $type = null): mixed
    {
        return \Temporal\Workflow::getLastCompletionResult($type);
    }

    /**
     * @param array<non-empty-string, mixed> $values
     */
    public static function upsertMemo(array $values): void
    {
        \Temporal\Workflow::upsertMemo($values);
    }

    /**
     * @param array<non-empty-string, mixed> $searchAttributes
     */
    public static function upsertSearchAttributes(array $searchAttributes): void
    {
        \Temporal\Workflow::upsertSearchAttributes($searchAttributes);
    }

    public static function upsertTypedSearchAttributes(SearchAttributeUpdate ...$updates): void
    {
        \Temporal\Workflow::upsertTypedSearchAttributes(...$updates);
    }

    /**
     * @param non-empty-string $queryType
     */
    public static function registerQuery(
        string $queryType,
        callable $handler,
        string $description = '',
    ): ScopedContextInterface {
        return \Temporal\Workflow::registerQuery($queryType, $handler, $description);
    }

    /**
     * @param non-empty-string $name
     */
    public static function registerSignal(
        string $name,
        callable $handler,
        string $description = '',
    ): ScopedContextInterface {
        return \Temporal\Workflow::registerSignal($name, $handler, $description);
    }

    /**
     * @param non-empty-string $name
     */
    public static function registerUpdate(
        string $name,
        callable $handler,
        ?callable $validator = null,
        string $description = '',
    ): ScopedContextInterface {
        return \Temporal\Workflow::registerUpdate($name, $handler, $validator, $description);
    }

    public static function registerDynamicSignal(callable $handler): WorkflowContextInterface
    {
        return \Temporal\Workflow::registerDynamicSignal($handler);
    }

    public static function registerDynamicQuery(callable $handler): WorkflowContextInterface
    {
        return \Temporal\Workflow::registerDynamicQuery($handler);
    }

    public static function registerDynamicUpdate(callable $handler, ?callable $validator = null): WorkflowContextInterface
    {
        return \Temporal\Workflow::registerDynamicUpdate($handler, $validator);
    }

    /**
     * @template TReturn
     * @param callable(): TReturn $task
     * @return CancellationScopeInterface<TReturn>
     */
    public static function async(callable $task): CancellationScopeInterface
    {
        return \Temporal\Workflow::async($task);
    }

    /**
     * @template TReturn
     * @param callable(): TReturn $task
     * @return CancellationScopeInterface<TReturn>
     */
    public static function asyncDetached(callable $task): CancellationScopeInterface
    {
        return \Temporal\Workflow::asyncDetached($task);
    }

    public static function await(callable|BaseMutex|Mutex|PromiseInterface ...$conditions): mixed
    {
        return FiberHelper::await(\Temporal\Workflow::await(...self::unwrapConditions($conditions)));
    }

    /**
     * @param \DateInterval|string|int $interval
     */
    public static function awaitWithTimeout($interval, callable|BaseMutex|Mutex|PromiseInterface ...$conditions): bool
    {
        /** @psalm-suppress MixedReturnStatement */
        return FiberHelper::await(
            \Temporal\Workflow::awaitWithTimeout($interval, ...self::unwrapConditions($conditions)),
        );
    }

    public static function getVersion(string $changeId, int $minSupported, int $maxSupported): int
    {
        /** @psalm-suppress MixedReturnStatement */
        return FiberHelper::await(\Temporal\Workflow::getVersion($changeId, $minSupported, $maxSupported));
    }

    /**
     * @template TReturn
     * @param callable(): TReturn $value
     * @return TReturn
     */
    public static function sideEffect(callable $value, ?SideEffectOptions $options = null): mixed
    {
        /** @psalm-suppress MixedReturnStatement */
        return FiberHelper::await(\Temporal\Workflow::sideEffect($value, $options));
    }

    /**
     * @param \DateInterval|string|int $interval
     */
    public static function timer($interval, ?TimerOptions $options = null): void
    {
        FiberHelper::await(\Temporal\Workflow::timer($interval, $options));
    }

    /**
     * Returns the raw, unawaited timer promise.
     *
     * Asymmetry: this is the only Fiber-mode operation that exposes a `xxxPromise()`
     * variant; it exists because `awaitWithTimeout()` and `Promise::race()` take a
     * promise as a deadline. For everything else, drop down to
     * `\Temporal\Workflow::xxx(...)` directly to get the raw promise.
     *
     * @param \DateInterval|string|int $interval
     * @return PromiseInterface<null>
     */
    public static function timerPromise($interval, ?TimerOptions $options = null): PromiseInterface
    {
        return \Temporal\Workflow::timer($interval, $options);
    }

    public static function continueAsNew(
        string $type,
        array $args = [],
        ?ContinueAsNewOptions $options = null,
    ): mixed {
        return FiberHelper::await(\Temporal\Workflow::continueAsNew($type, $args, $options));
    }

    /**
     * @param non-empty-string $type
     * @param list<mixed> $args
     * @param Type|string|\ReflectionType|\ReflectionClass|null $returnType
     */
    public static function executeChildWorkflow(
        string $type,
        array $args = [],
        ?ChildWorkflowOptions $options = null,
        mixed $returnType = null,
    ): mixed {
        return FiberHelper::await(\Temporal\Workflow::executeChildWorkflow($type, $args, $options, $returnType));
    }

    /**
     * @param non-empty-string $type
     * @param list<mixed> $args
     */
    public static function executeActivity(
        string $type,
        array $args = [],
        ?ActivityOptionsInterface $options = null,
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
    ): mixed {
        /** @psalm-suppress ArgumentTypeCoercion */
        return FiberHelper::await(\Temporal\Workflow::executeActivity($type, $args, $options, $returnType));
    }

    public static function uuid(): UuidInterface
    {
        /** @psalm-suppress MixedReturnStatement */
        return FiberHelper::await(\Temporal\Workflow::uuid());
    }

    public static function uuid4(): UuidInterface
    {
        /** @psalm-suppress MixedReturnStatement */
        return FiberHelper::await(\Temporal\Workflow::uuid4());
    }

    public static function uuid7(?\DateTimeInterface $dateTime = null): UuidInterface
    {
        /** @psalm-suppress MixedReturnStatement */
        return FiberHelper::await(\Temporal\Workflow::uuid7($dateTime));
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     * @psalm-suppress InvalidReturnType,InvalidReturnStatement
     */
    public static function newActivityStub(
        string $class,
        ?ActivityOptionsInterface $options = null,
    ): object {
        return new FiberProxy(\Temporal\Workflow::newActivityStub($class, $options));
    }

    public static function newUntypedActivityStub(
        ?ActivityOptionsInterface $options = null,
    ): FiberActivityStubInterface {
        return new FiberActivityStub(
            \Temporal\Workflow::newUntypedActivityStub($options),
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     * @psalm-suppress InvalidReturnType,InvalidReturnStatement
     */
    public static function newChildWorkflowStub(
        string $class,
        ?ChildWorkflowOptions $options = null,
    ): object {
        return new FiberProxy(\Temporal\Workflow::newChildWorkflowStub($class, $options));
    }

    public static function newUntypedChildWorkflowStub(
        string $name,
        ?ChildWorkflowOptions $options = null,
    ): FiberChildWorkflowStubInterface {
        return new FiberChildWorkflowStub(
            \Temporal\Workflow::newUntypedChildWorkflowStub($name, $options),
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     * @psalm-suppress InvalidReturnType,InvalidReturnStatement
     */
    public static function newContinueAsNewStub(string $class, ?ContinueAsNewOptions $options = null): object
    {
        return new FiberProxy(\Temporal\Workflow::newContinueAsNewStub($class, $options));
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     * @psalm-suppress InvalidReturnType,InvalidReturnStatement
     */
    public static function newExternalWorkflowStub(string $class, WorkflowExecution $execution): object
    {
        return new FiberProxy(\Temporal\Workflow::newExternalWorkflowStub($class, $execution));
    }

    public static function newUntypedExternalWorkflowStub(WorkflowExecution $execution): FiberExternalWorkflowStubInterface
    {
        return new FiberExternalWorkflowStub(
            \Temporal\Workflow::newUntypedExternalWorkflowStub($execution),
        );
    }

    /**
     * Run a function while holding a mutex lock.
     *
     * @template T
     * @param callable(): T $callable
     * @return CancellationScopeInterface<T>
     */
    public static function runLocked(Mutex|BaseMutex $mutex, callable $callable): CancellationScopeInterface
    {
        return self::async(static function () use ($mutex, $callable): mixed {
            $lockResult = $mutex->lock();
            if ($lockResult instanceof PromiseInterface) {
                FiberHelper::await($lockResult);
            }

            try {
                $result = $callable();
                if ($result instanceof PromiseInterface) {
                    $result = FiberHelper::await($result);
                }
                return $result;
            } finally {
                $mutex->unlock();
            }
        });
    }

    /**
     * Execute multiple tasks in parallel and wait for all results.
     *
     * ```php
     *  [$a, $b] = Workflow::gather(
     *      fn() => $activity->methodA(),
     *      fn() => $activity->methodB(),
     *  );
     * ```
     *
     * The helper does not expose the underlying scopes; outer cancellation stops
     * further iteration but cannot individually cancel in-flight inner scopes. If
     * you need per-task cancellation hold the `async()` scopes yourself.
     *
     * @param callable(): mixed ...$tasks
     * @return list<mixed>
     */
    public static function gather(callable ...$tasks): array
    {
        $scopes = \array_map(static fn(callable $task) => self::async($task), $tasks);

        /** @psalm-suppress PossiblyInvalidArgument */
        return Promise::all($scopes);
    }

    /**
     * Unwrap any {@see Mutex} into its underlying {@see BaseMutex} so the base
     * {@see \Temporal\Workflow::await()} contract never sees the experimental type.
     *
     * @param array<callable|BaseMutex|Mutex|PromiseInterface> $conditions
     * @return list<callable|BaseMutex|PromiseInterface>
     */
    private static function unwrapConditions(array $conditions): array
    {
        $unwrapped = [];
        foreach ($conditions as $condition) {
            $unwrapped[] = $condition instanceof Mutex ? $condition->getInner() : $condition;
        }

        return $unwrapped;
    }
}
