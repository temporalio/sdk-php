<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use Temporal\Workflow\TimerOptions;

/**
 * @internal
 * @experimental This API is experimental and may change in the future.
 */
class AwaitOptions
{
    public function __construct(
        /**
         * Await timeout.
         */
        public readonly \DateInterval $interval,

        /**
         * Options set for the underlying timer created.
         */
        public readonly ?TimerOptions $options,
    ) {}
}
