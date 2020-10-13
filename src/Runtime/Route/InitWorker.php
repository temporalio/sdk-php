<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime\Route;

use React\Promise\Deferred;

class InitWorker extends Route
{
    public function handle(array $params, Deferred $resolver): void
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }
}
