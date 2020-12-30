<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use Temporal\Internal\Coroutine\CoroutineInterface;
use Temporal\Workflow\WorkflowContextInterface;

trait ScopeAwareTrait
{
    /**
     * @var WorkflowContextInterface
     */
    protected WorkflowContextInterface $context;

    /**
     * @param \Closure $handler
     * @param array $args
     * @return \Generator
     */
    protected function call(\Closure $handler, array $args): \Generator
    {
        $this->context->makeCurrent();

        $result = $handler(...$args);

        if ($result instanceof \Generator || $result instanceof CoroutineInterface) {
            yield from $result;

            return $result->getReturn();
        }

        return $result;
    }
}
