<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Temporal\Client\Future;

use React\Promise\CancellablePromiseInterface;
use React\Promise\PromisorInterface;

interface FutureInterface extends PromisorInterface, CancellablePromiseInterface
{
    /**
     * @return bool
     */
    public function isComplete(): bool;
}
