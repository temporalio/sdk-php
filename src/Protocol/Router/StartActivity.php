<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Router;

use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Command\ResponseInterface;

final class StartActivity extends Route
{
    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers)
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }
}
