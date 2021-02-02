<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

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
    public function async(callable $handler): CancellationScopeInterface;

    /**
     * Cancellation scope which does not react to parent cancel and completes in background.
     *
     * @param callable $handler
     * @return CancellationScopeInterface
     */
    public function asyncDetached(callable $handler): CancellationScopeInterface;
}
