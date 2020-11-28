<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Execution;

use Temporal\Client\Internal\Coroutine\CoroutineInterface;
use Temporal\Client\Internal\Coroutine\Stack;

class RootExecutionContext extends ExecutionContext
{
    /**
     * @var Stack
     */
    private Stack $stack;

    /**
     * @param callable $handler
     * @param array $arguments
     */
    public function __construct(callable $handler, array $arguments = [])
    {
        parent::__construct($handler, $arguments);

        $this->stack = new Stack($this->getProcess());
    }

    /**
     * @param CoroutineInterface $coroutine
     * @param \Closure|null $onComplete
     * @return $this
     */
    public function attach(CoroutineInterface $coroutine, \Closure $onComplete = null): self
    {
        $this->stack->push($coroutine, $onComplete);

        return $this;
    }
}
