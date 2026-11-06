<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

use JetBrains\PhpStorm\Immutable;
use Temporal\DataConverter\EncodedCollection;

/**
 * @see \Temporal\Api\Activity\V1\ActivityExecutionListInfo
 * @psalm-immutable
 */
#[Immutable]
final class ActivityExecutionInfo
{
    /**
     * @internal
     */
    public function __construct(
        public readonly string $activityId,
        public readonly string $runId,
        public readonly string $activityType,
        public readonly ActivityExecutionStatus $status,
        public readonly string $taskQueue,
        public readonly ?\DateTimeInterface $scheduleTime,
        public readonly ?\DateTimeInterface $executionTime,
        public readonly ?\DateTimeInterface $closeTime,
        public readonly ?\DateInterval $executionDuration,
        public readonly EncodedCollection $searchAttributes,
    ) {}
}
