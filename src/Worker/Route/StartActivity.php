<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Route;

use React\Promise\Deferred;

final class StartActivity extends Route
{
    public function handle(array $params, Deferred $resolver): void
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }
}
