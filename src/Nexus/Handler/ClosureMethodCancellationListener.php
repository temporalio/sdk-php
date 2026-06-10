<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

final class ClosureMethodCancellationListener implements MethodCancellationListenerInterface
{
    private function __construct(
        private readonly \Closure $closure,
    ) {}

    public static function fromCallable(callable $callable): self
    {
        return new self($callable(...));
    }

    public function cancelled(): void
    {
        ($this->closure)();
    }
}
