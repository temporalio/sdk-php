<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Context;

use JetBrains\PhpStorm\ExpectedValues;
use React\Promise\PromiseInterface;
use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Internal\Support\DateInterval;
use Temporal\Client\Internal\Transport\ClientInterface;

/**
 * @psalm-import-type DateIntervalFormat from DateInterval
 */
interface RequestsInterface extends ClientInterface
{
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
     * @psalm-template ActivityType
     * @psalm-param class-string<ActivityType> $name
     * @psalm-return ActivityType
     *
     * @param string $name
     * @return object
     */
    public function newActivityStub(
        string $name,
        #[ExpectedValues(values: ActivityOptions::class)]
        $options = null
    ): object;

    /**
     * @param DateIntervalFormat $interval
     * @return PromiseInterface
     * @see DateInterval
     */
    public function timer($interval): PromiseInterface;
}
