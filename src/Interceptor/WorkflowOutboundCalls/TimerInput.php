<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use Temporal\Workflow\TimerOptions;

/**
 * @psalm-immutable
 */
final class TimerInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly \DateInterval $interval,

        /**
         * @experimental This API is experimental and may change in the future.
         */
        public readonly ?TimerOptions $timerOptions,
    ) {}

    public function with(
        ?\DateInterval $interval = null,
    ): self {
        return new self(
            $interval ?? $this->interval,
            $this->timerOptions,
        );
    }
}
