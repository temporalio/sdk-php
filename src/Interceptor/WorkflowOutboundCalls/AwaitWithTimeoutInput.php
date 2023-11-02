<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use DateInterval;
use React\Promise\PromiseInterface;

/**
 * @psalm-immutable
 */
final class AwaitWithTimeoutInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     *
     * @param array<callable|PromiseInterface> $conditions
     */
    public function __construct(
        public readonly DateInterval $interval,
        public readonly array $conditions,
    ) {
    }

    /**
     * @param array<callable|PromiseInterface> $conditions
     */
    public function with(
        ?DateInterval $interval = null,
        ?array $conditions = null,
    ): self {
        return new self(
            $interval ?? $this->interval,
            $conditions ?? $this->conditions,
        );
    }
}
