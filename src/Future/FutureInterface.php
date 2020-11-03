<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Temporal\Client\Future;

use React\Promise\PromisorInterface;

interface FutureInterface extends PromisorInterface
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
}
