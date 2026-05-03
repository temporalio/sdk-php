<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

/**
 * Adapter that wraps a `callable` into a SynchronousOperationFunctionInterface.
 *
 * @implements SynchronousOperationFunctionInterface<mixed, mixed>
 */
final class ClosureOperationFunction implements SynchronousOperationFunctionInterface
{
    private function __construct(
        private readonly \Closure $closure,
    ) {}

    public static function fromCallable(callable $callable): self
    {
        return new self($callable(...));
    }

    public function __invoke(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $input,
    ): mixed {
        return ($this->closure)($context, $details, $input);
    }

    /**
     * @internal Reflection hook for {@see \Temporal\Nexus\Handler\Internal\ClosureTypeValidator}.
     */
    public function getClosure(): \Closure
    {
        return $this->closure;
    }
}
