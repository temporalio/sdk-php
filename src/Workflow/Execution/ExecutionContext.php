<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Execution;

use React\Promise\Promise;
use Temporal\Client\Exception\CancellationException;
use Temporal\Client\Internal\Coroutine\Coroutine;
use Temporal\Client\Internal\Coroutine\CoroutineInterface;

class ExecutionContext extends Promise
{
    /**
     * @var CoroutineInterface
     */
    private CoroutineInterface $process;

    /**
     * @var \Closure
     */
    private \Closure $resolve;

    /**
     * @var \Closure
     */
    private \Closure $reject;

    /**
     * @param callable $handler
     * @param array $arguments
     */
    public function __construct(callable $handler, array $arguments = [])
    {
        $this->process = Coroutine::create(
            $this->start($handler, $arguments)
        );

        parent::__construct(
            \Closure::fromCallable([$this, 'resolver']),
            \Closure::fromCallable([$this, 'onCancel'])
        );
    }

    /**
     * @return CoroutineInterface
     */
    public function getProcess(): CoroutineInterface
    {
        return $this->process;
    }

    /**
     * @return \Generator
     */
    private function start(callable $handler, array $arguments): \Generator
    {
        $result = $handler($arguments);

        $isCoroutine = $result instanceof \Generator
            || $result instanceof CoroutineInterface
        ;

        if ($isCoroutine) {
            yield from $result;

            return $result->getReturn();
        }

        return $result;
    }

    /**
     * @param mixed $value
     * @return void
     */
    protected function resolve($value): void
    {
        ($this->resolve)($value);
    }

    /**
     * @param \Throwable $e
     * @return void
     */
    protected function reject(\Throwable $e): void
    {
        ($this->reject)($e);
    }

    /**
     * @param \Closure(mixed) $resolve
     * @param \Closure(\Throwable) $reject
     * @return void
     */
    protected function resolver(\Closure $onFulfilled, \Closure $onRejected): void
    {
        [$this->resolve, $this->reject] = [$onFulfilled, $onRejected];
    }

    /**
     * @return \Closure
     */
    protected function onCancel(): void
    {
        throw new CancellationException('Cancelled');
    }
}
