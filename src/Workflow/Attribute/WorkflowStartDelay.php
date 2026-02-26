<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow\Attribute;

use Spiral\Attributes\NamedArgumentConstructor;
use Temporal\Internal\Support\DateInterval;

/**
 * Time to wait before dispatching the first workflow task.
 *
 * @psalm-import-type DateIntervalValue from \Temporal\Internal\Support\DateInterval
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class WorkflowStartDelay
{
    public readonly \DateInterval $interval;

    /**
     * @param DateIntervalValue $delay
     */
    public function __construct(mixed $delay)
    {
        $this->interval = DateInterval::parse($delay, DateInterval::FORMAT_SECONDS);

        if ($this->interval->totalMicroseconds < 0) {
            throw new \InvalidArgumentException('WorkflowStartDelay must be non-negative.');
        }
    }
}
