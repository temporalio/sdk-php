<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;

interface CancellationScopeInterface extends PromiseInterface, CancellablePromiseInterface
{
    /**
     * Detached scopes can continue working even if parent scope was cancelled.
     *
     * @return bool
     */
    public function isDetached(): bool;

    /**
     * Returns true if cancel request was sent to scope.
     *
     * @return bool
     */
    public function isCancelled(): bool;

    /**
     * Triggered when cancel request sent to scope.
     *
     * @param callable $then
     * @return $this
     */
    public function onCancel(callable $then): self;
}
