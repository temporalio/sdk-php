<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\Exception\LogicException;
use Temporal\Nexus\Exception\OperationException;

/**
 * Handler for a synchronous operation. Plain callables are wrapped in
 * {@see ClosureOperationFunction}.
 *
 * @template T
 * @template R
 * @implements OperationHandlerInterface<T, R>
 */
final class SynchronousOperationHandler implements OperationHandlerInterface
{
    /** @var SynchronousOperationFunctionInterface<T, R> */
    private readonly SynchronousOperationFunctionInterface $function;

    /**
     * @param SynchronousOperationFunctionInterface<T, R>|callable(OperationContext, OperationStartDetails, T|null): (R|null) $function
     */
    public function __construct(
        callable|SynchronousOperationFunctionInterface $function,
    ) {
        $this->function = $function instanceof SynchronousOperationFunctionInterface
            ? $function
            : ClosureOperationFunction::fromCallable($function);
    }

    /**
     * @template TI
     * @template TR
     * @param callable(OperationContext, OperationStartDetails, TI|null): (TR|null) $function
     * @return self<TI, TR>
     */
    public static function fromCallable(callable $function): self
    {
        return new self($function);
    }

    /**
     * @template TI
     * @template TR
     * @param SynchronousOperationFunctionInterface<TI, TR> $function
     * @return self<TI, TR>
     */
    public static function fromFunction(SynchronousOperationFunctionInterface $function): self
    {
        return new self($function);
    }

    /**
     * @internal Reflection hook for {@see \Temporal\Nexus\Handler\Internal\ClosureTypeValidator}.
     */
    public function getFunction(): SynchronousOperationFunctionInterface
    {
        return $this->function;
    }

    /**
     * @param T|null $param
     * @return OperationStartResult<R>
     *
     * @throws OperationException
     */
    public function start(
        OperationContext $context,
        OperationStartDetails $details,
        mixed $param,
    ): OperationStartResult {
        return OperationStartResult::sync(($this->function)($context, $details, $param));
    }

    public function cancel(
        OperationContext $context,
        OperationCancelDetails $details,
    ): void {
        throw new LogicException('cancel() is not supported on synchronous operations');
    }
}
