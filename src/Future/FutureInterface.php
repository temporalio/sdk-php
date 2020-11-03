<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Temporal\Client\Future;

use React\Promise\PromiseInterface;

interface FutureInterface
{
    /**
     * @param callable $onComplete
     * @return FutureInterface
     */
    public function onComplete(callable $onComplete);

    /**
     * @return bool
     */
    public function isComplete(): bool;

    /**
     * @return mixed
     */
    public function cancel();

    /**
     * Returns underlying promise. Attention, promises resolved immediately as data arrives.
     *
     * @return PromiseInterface
     */
    public function promise(): PromiseInterface;
}
