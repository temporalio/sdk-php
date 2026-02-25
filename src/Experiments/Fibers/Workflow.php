<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptionsInterface;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate;
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
 * Fiber-based Workflow facade.
 *
 * Drop-in replacement for {@see \Temporal\Workflow} that auto-suspends Fibers
 * on async operations. Workflow code can be written as plain PHP without
 * yield/Generator.
 *
 * Migration: replace `use Temporal\Workflow` with `use Temporal\Experiments\Fibers\Workflow`,
 * remove `yield` and `\Generator` return types.
 *
 * @experimental This API is experimental and may change in future releases.
 */
final class Workflow
{
    private function __construct() {}

    // =========================================================================
    // Context & info (direct pass-through)
    // =========================================================================

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

    /**
     * @param Type|mixed $type
     */
    public static function getLastCompletionResult($type = null): mixed
    {
        return \Temporal\Workflow::getLastCompletionResult($type);
    }

    // =========================================================================
    // Memos & search attributes (direct pass-through)
    // =========================================================================

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

    // =========================================================================
    // Registration (direct pass-through)
    // =========================================================================

    public static function registerQuery(
        string $queryType,
        callable $handler,
        string $description = '',
    ): ScopedContextInterface {
        return \Temporal\Workflow::registerQuery($queryType, $handler, $description);
    }

    public static function registerSignal(
        string $name,
        callable $handler,
        string $description = '',
    ): ScopedContextInterface {
        return \Temporal\Workflow::registerSignal($name, $handler, $description);
    }

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

    // =========================================================================
    // Async scopes (direct pass-through)
    // =========================================================================

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

    // =========================================================================
    // Async operations (auto-suspend via FiberHelper)
    // =========================================================================

    public static function await(callable|BaseMutex|Mutex|PromiseInterface ...$conditions): mixed
    {
        return FiberHelper::await(\Temporal\Workflow::await(...$conditions));
    }

    /**
     * @param \DateInterval|string|int $interval
     */
    public static function awaitWithTimeout($interval, callable|BaseMutex|PromiseInterface ...$conditions): mixed
    {
        return FiberHelper::await(\Temporal\Workflow::awaitWithTimeout($interval, ...$conditions));
    }

    public static function getVersion(string $changeId, int $minSupported, int $maxSupported): mixed
    {
        return FiberHelper::await(\Temporal\Workflow::getVersion($changeId, $minSupported, $maxSupported));
    }

    /**
     * @template TReturn
     * @param callable(): TReturn $value
     */
    public static function sideEffect(callable $value): mixed
    {
        return FiberHelper::await(\Temporal\Workflow::sideEffect($value));
    }

    /**
     * @param \DateInterval|string|int $interval
     */
    public static function timer($interval, ?TimerOptions $options = null): mixed
    {
        return FiberHelper::await(\Temporal\Workflow::timer($interval, $options));
    }
    /**
     * @param \DateInterval|string|int $interval
     * @return PromiseInterface<void>
     */
    public static function createTimer($interval, ?TimerOptions $options = null): PromiseInterface
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

    public static function executeChildWorkflow(
        string $type,
        array $args = [],
        ?ChildWorkflowOptions $options = null,
        mixed $returnType = null,
    ): mixed {
        return FiberHelper::await(\Temporal\Workflow::executeChildWorkflow($type, $args, $options, $returnType));
    }

    public static function executeActivity(
        string $type,
        array $args = [],
        ?ActivityOptionsInterface $options = null,
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
    ): mixed {
        return FiberHelper::await(\Temporal\Workflow::executeActivity($type, $args, $options, $returnType));
    }

    public static function uuid(): mixed
    {
        return FiberHelper::await(\Temporal\Workflow::uuid());
    }

    public static function uuid4(): mixed
    {
        return FiberHelper::await(\Temporal\Workflow::uuid4());
    }

    public static function uuid7(?\DateTimeInterface $dateTime = null): mixed
    {
        return FiberHelper::await(\Temporal\Workflow::uuid7($dateTime));
    }

    // =========================================================================
    // Proxy factories (return FiberProxy wrappers)
    // =========================================================================

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public static function newActivityStub(
        string $class,
        ?ActivityOptionsInterface $options = null,
    ): object {
        return new FiberProxy(\Temporal\Workflow::newActivityStub($class, $options));
    }

    public static function newUntypedActivityStub(
        ?ActivityOptionsInterface $options = null,
    ): FiberActivityStub {
        return new FiberActivityStub(
            \Temporal\Workflow::newUntypedActivityStub($options),
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
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
    ): FiberChildWorkflowStub {
        return new FiberChildWorkflowStub(
            \Temporal\Workflow::newUntypedChildWorkflowStub($name, $options),
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public static function newContinueAsNewStub(string $class, ?ContinueAsNewOptions $options = null): object
    {
        return new FiberProxy(\Temporal\Workflow::newContinueAsNewStub($class, $options));
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public static function newExternalWorkflowStub(string $class, WorkflowExecution $execution): object
    {
        return new FiberProxy(\Temporal\Workflow::newExternalWorkflowStub($class, $execution));
    }

    public static function newUntypedExternalWorkflowStub(WorkflowExecution $execution): FiberExternalWorkflowStub
    {
        return new FiberExternalWorkflowStub(
            \Temporal\Workflow::newUntypedExternalWorkflowStub($execution),
        );
    }

    // =========================================================================
    // Convenience methods
    // =========================================================================

    /**
     * Run a function while holding a mutex lock.
     *
     * @template T
     * @param Mutex|BaseMutex $mutex
     * @param callable(): T $callable
     * @return CancellationScopeInterface<T>
     */
    public static function runLocked(Mutex|BaseMutex $mutex, callable $callable): CancellationScopeInterface
    {
        return self::async(static function () use ($mutex, $callable): mixed {
            if ($mutex instanceof Mutex) {
                $mutex->lock();
            } else {
                FiberHelper::await($mutex->lock());
            }

            try {
                return $callable();
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
     * @return array<mixed>
     */
    public static function gather(callable ...$tasks): mixed
    {
        $scopes = \array_map(static fn(callable $task) => self::async($task), $tasks);

        return Promise::all($scopes);
    }
}
