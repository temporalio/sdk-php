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
    public function onComplete(callable $onComplete): FutureInterface;

    public function isComplete(): bool;

    public function cancel();

    /**
     * Returns underlying promise. Attention, promises resolved immediately as data arrives.
     *
     * @return PromiseInterface
     */
    public function promise(): PromiseInterface;
}
