<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Runtime;

use JetBrains\PhpStorm\ExpectedValues;
use React\Promise\PromiseInterface;
use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Workflow\Command\NewTimer;

interface WorkflowExecutionsInterface
{
    /**
     * @template ActivityType
     * @psalm-param class-string<ActivityType> $name
     * @psalm-return ActivityType|ActivityProxy<ActivityType>
     *
     * @param string $name
     * @return ActivityProxy
     */
    public function activity(string $name): ActivityProxy;

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
     * @psalm-import-type DateIntervalFormat from NewTimer
     *
     * @param string|int|\DateInterval $interval
     * @return PromiseInterface
     * @see NewTimer
     */
    public function timer($interval): PromiseInterface;
}
