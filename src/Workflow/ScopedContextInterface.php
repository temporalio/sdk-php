<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Temporal\Workflow;

/**
 * Handles scope creation.
 */
interface ScopedContextInterface extends WorkflowContextInterface
{
    /**
     * The method calls an asynchronous task and returns a promise.
     *
     * @see Workflow::async()
     */
    public function async(callable $handler): CancellationScopeInterface;

    /**
     * Cancellation scope which does not react to parent cancel and completes
     * in background.
     *
     * @see Workflow::asyncDetached()
     */
    public function asyncDetached(callable $handler): CancellationScopeInterface;
}
