<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use JetBrains\PhpStorm\ExpectedValues;
use React\Promise\PromiseInterface;
use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Worker\EnvironmentInterface;
use Temporal\Client\Workflow\Execution\ExecutionContext;

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
     * @template ActivityType
     * @psalm-param class-string<ActivityType> $name
     * @psalm-return ActivityType|ActivityProxy<ActivityType>
     *
     * @param string $name
     * @return ActivityProxy
     */
    public function newActivityStub(string $name): ActivityProxy;

    /**
     * @param string $changeId
     * @param int $minSupported
     * @param int $maxSupported
     * @return PromiseInterface
     */
    public function getVersion(string $changeId, int $minSupported, int $maxSupported): PromiseInterface;

    /**
     * @psalm-type SideEffectCallback = callable(): mixed
     * @psalm-param SideEffectCallback $cb
     *
     * @param callable $cb
     * @return PromiseInterface
     */
    public function sideEffect(callable $cb): PromiseInterface;

    /**
     * @param mixed $result
     * @return PromiseInterface
     */
    public function complete($result = null): PromiseInterface;

    /**
     * @psalm-param class-string|string $name
     *
     * @param string $name
     * @param array $arguments
     * @param ActivityOptions|array|null $options
     * @return PromiseInterface
     */
    public function executeActivity(
        string $name,
        array $arguments = [],
        #[ExpectedValues(values: ActivityOptions::class)]
        $options = null
    ): PromiseInterface;

    /**
     * @param string|int|float|\DateInterval $interval
     * @return PromiseInterface
     * @see DateInterval
     *
     * @psalm-import-type DateIntervalFormat from DateInterval
     */
    public function timer($interval): PromiseInterface;

    /**
     * @return array
     */
    public function getDebugBacktrace(): array;

    /**
     * @param callable $handler
     * @return ExecutionContext
     */
    public function newCancellationScope(callable $handler): ExecutionContext;
}
