<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Activity\Attribute;

use Spiral\Attributes\NamedArgumentConstructor;

/**
 * Task queue that the activity needs to be scheduled on.
 *
 * Optional: The default task queue with the same name as the workflow task queue.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class TaskQueue
{
    public function __construct(
        public readonly string $name,
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('TaskQueue name must not be empty.');
        }
    }
}
