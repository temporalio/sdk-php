<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

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
    ) {
    }

    public function with(
        ?\DateInterval $interval = null,
    ): self {
        return new self(
            $interval ?? $this->interval,
        );
    }
}
