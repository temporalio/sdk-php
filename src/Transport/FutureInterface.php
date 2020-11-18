<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport;

use React\Promise\CancellablePromiseInterface;
use React\Promise\PromisorInterface;

interface FutureInterface extends PromisorInterface, CancellablePromiseInterface
{
    /**
     * @return bool
     */
    public function isComplete(): bool;
}
