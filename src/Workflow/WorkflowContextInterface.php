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
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Support\DateInterval;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Environment\EnvironmentInterface;

/**
 * @psalm-import-type DateIntervalFormat from DateInterval
 */
interface WorkflowContextInterface extends EnvironmentInterface
{
    /**
     * @return WorkflowInfo
     */
    public function getInfo(): WorkflowInfo;

    /**
     * @return ValuesInterface
     */
    public function getInput(): ValuesInterface;

    /**
     * Get value of last completion result, if any.
     *
     * @param Type|string $type
     * @return mixed
     */
    public function getLastCompletionResult($type = null);

    /**
     * @return DataConverterInterface
     */
    public function getDataConverter(): DataConverterInterface;

    /**
     * @param string $queryType
     * @param callable $handler
     * @return $this
     */
    public function registerQuery(string $queryType, callable $handler): self;

    /**
     * @param string $queryType
     * @param callable $handler
     * @return $this
     */
    public function registerSignal(string $queryType, callable $handler): self;

    /**
     * Exchanges data between worker and host process.
     *
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function request(RequestInterface $request): PromiseInterface;

    /**
     * @param string $changeId
     * @param int $minSupported
     * @param int $maxSupported
     * @return PromiseInterface
     */
    public function getVersion(string $changeId, int $minSupported, int $maxSupported): PromiseInterface;

    /**
     * @psalm-type SideEffectCallback = callable(): mixed
     * @psalm-param SideEffectCallback $context
     *
     * @param callable $context
     * @return PromiseInterface
     */
    public function sideEffect(callable $context): PromiseInterface;

    /**
     * @param array|null $result
     * @param \Throwable|null $failure
     * @return PromiseInterface
     */
    public function complete(array $result = null, \Throwable $failure = null): PromiseInterface;

    /**
     * @param DateIntervalFormat|int $interval
     * @return PromiseInterface
     * @see DateInterval
     */
    public function timer($interval): PromiseInterface;

    /**
     * @param class-string|string $type
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
     * @psalm-template T of object
     * @psalm-param class-string<T> $type
     * @psalm-return object<T>|T
     *
     * @param string $type
     * @param ContinueAsNewOptions|null $options
     * @return object
     */
    public function newContinueAsNewStub(
        string $type,
        ContinueAsNewOptions $options = null
    ): object;

    /**
     * @param class-string|string $type
     * @param array $args
     * @param ChildWorkflowOptions|null $options
     * @param \ReflectionType|null $returnType
     * @return PromiseInterface
     */
    public function executeChildWorkflow(
        string $type,
        array $args = [],
        ChildWorkflowOptions $options = null,
        \ReflectionType $returnType = null
    ): PromiseInterface;

    /**
     * @psalm-template T of object
     * @psalm-param class-string<T> $type
     * @psalm-return object<T>|T
     *
     * @param string $type
     * @param ChildWorkflowOptions|null $options
     * @return object
     */
    public function newChildWorkflowStub(
        string $type,
        ChildWorkflowOptions $options = null
    ): object;

    /**
     * @param string $name
     * @param ChildWorkflowOptions|null $options
     * @return ChildWorkflowStubInterface
     */
    public function newUntypedChildWorkflowStub(
        string $name,
        ChildWorkflowOptions $options = null
    ): ChildWorkflowStubInterface;


    /**
     * Creates client stub that can be used to communicate to an existing
     * workflow execution.
     *
     * @psalm-template T of object
     * @psalm-param class-string<T> $type
     * @psalm-return object<T>|T
     *
     * @param string $type
     * @param WorkflowExecution $execution
     * @return object
     */
    public function newExternalWorkflowStub(string $type, WorkflowExecution $execution): object;

    /**
     * Creates untyped client stub that can be used to signal or cancel a child
     * workflow.
     *
     * @param WorkflowExecution $execution
     * @return ExternalWorkflowStubInterface
     */
    public function newUntypedExternalWorkflowStub(WorkflowExecution $execution): ExternalWorkflowStubInterface;

    /**
     * @param string $type
     * @param array $args
     * @param ActivityOptions|null $options
     * @param \ReflectionType|null $returnType
     * @return PromiseInterface
     */
    public function executeActivity(
        string $type,
        array $args = [],
        ActivityOptions $options = null,
        \ReflectionType $returnType = null
    ): PromiseInterface;

    /**
     * @psalm-template TActivity
     * @psalm-param class-string<TActivity> $class
     * @psalm-return TActivity
     *
     * @param string $class
     * @param ActivityOptions|null $options
     * @return object
     */
    public function newActivityStub(
        string $class,
        ActivityOptions $options = null
    ): object;

    /**
     * @param ActivityOptions|null $options
     * @return ActivityStubInterface
     */
    public function newUntypedActivityStub(
        ActivityOptions $options = null
    ): ActivityStubInterface;

    /**
     * @return string
     */
    public function getStackTrace(): string;
}
