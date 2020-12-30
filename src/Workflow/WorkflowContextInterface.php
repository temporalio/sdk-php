<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Internal\Support\DateInterval;
use Temporal\Client\Worker\Environment\EnvironmentInterface;

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
     * @return array
     */
    public function getArguments(): array;

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
     * @param callable $handler
     * @return CancellationScopeInterface
     */
    public function newCancellationScope(callable $handler): CancellationScopeInterface;

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
     * @param mixed $result
     * @return PromiseInterface
     */
    public function complete($result = null): PromiseInterface;

    /**
     * @param string $name
     * @param mixed $input
     * @return PromiseInterface
     */
    public function continueAsNew(string $name, ...$input): PromiseInterface;

    /**
     * @param class-string|string $type
     * @param array $args
     * @param ChildWorkflowOptions|null $options
     * @return PromiseInterface
     */
    public function executeChildWorkflow(
        string $type,
        array $args = [],
        ChildWorkflowOptions $options = null
    ): PromiseInterface;

    /**
     * @psalm-template WorkflowType
     * @psalm-param class-string<WorkflowType> $class
     * @psalm-return WorkflowType
     *
     * @param string $class
     * @param ChildWorkflowOptions|null $options
     * @return object
     */
    public function newChildWorkflowStub(string $class, ChildWorkflowOptions $options = null): object;

    /**
     * @psalm-param class-string|string $type
     *
     * @param string $type
     * @param array $args
     * @param ActivityOptions|null $options
     * @return PromiseInterface
     */
    public function executeActivity(string $type, array $args = [], ActivityOptions $options = null): PromiseInterface;

    /**
     * @psalm-template ActivityType
     * @psalm-param class-string<ActivityType> $class
     * @psalm-return ActivityType
     *
     * @param string $class
     * @param ChildWorkflowOptions|null $options
     * @return object
     */
    public function newActivityStub(string $class, ActivityOptions $options = null): object;

    /**
     * @param DateIntervalFormat $interval
     * @return PromiseInterface
     * @see DateInterval
     */
    public function timer($interval): PromiseInterface;

    /**
     * @return array
     */
    public function getTrace(): array;
}
