<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\PromiseInterface;

/**
 * @template T
 * @yield T
 * @extends PromiseInterface<T>
 */
interface CancellationScopeInterface extends PromiseInterface
{
    /**
     * Detached scopes can continue working even if parent scope was cancelled.
     *
     */
    public function isDetached(): bool;

    /**
     * Returns true if cancel request was sent to scope.
     *
     */
    public function isCancelled(): bool;

    /**
     * Triggered when cancel request sent to scope.
     *
     * @return $this
     */
    public function onCancel(callable $then): self;

    /**
     * The `cancel()` method notifies the creator of the promise that there is no
     * further interest in the results of the operation.
     *
     * Once a promise is settled (either fulfilled or rejected), calling `cancel()` on
     * a promise has no effect.
     *
     */
    public function cancel(): void;
}
