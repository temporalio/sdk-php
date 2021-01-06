<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Workflow;

/**
 * Handles scope creation.
 */
interface ScopedContextInterface extends WorkflowContextInterface
{
    /**
     * @param callable $handler
     * @return CancellationScopeInterface
     */
    public function newCancellationScope(callable $handler): CancellationScopeInterface;

    /**
     * Cancellation scope which does not react to parent cancel and completes in background.
     *
     * @param callable $handler
     * @return CancellationScopeInterface
     */
    public function newDetachedCancellationScope(callable $handler): CancellationScopeInterface;
}
