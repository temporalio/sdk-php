<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Workflow;

use React\Promise\Promise;
use Temporal\Client\Internal\Coroutine\Coroutine;
use Temporal\Client\Internal\Coroutine\CoroutineInterface;

class CancellationScope extends Promise
{
    /**
     * @param callable $resolver
     */
    public function __construct(Process $process, callable $handler)
    {
        parent::__construct($this->handlerExecutor($process, $handler), $this->canceller());
    }

    /**
     * @param Process $process
     * @param callable $handler
     * @return \Closure
     */
    private function handlerExecutor(Process $process, callable $handler): \Closure
    {
        return static function ($resolve, $reject) use ($handler, $process) {
            try {
                $child = $handler();

                if ($child instanceof \Generator || $child instanceof CoroutineInterface) {
                    $process->attach(Coroutine::create($child), $resolve);
                } else {
                    $resolve($child);
                }

            } catch (\Throwable $e) {
                $reject($e);
            }
        };
    }

    /**
     * @return \Closure
     */
    private function canceller(): \Closure
    {
        return function () {
            //
            \error_log('CANCEL!!!!!!!!!!!!!!!!!!!!!!');
        };
    }
}
