<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport;

interface RoutableTransportInterface extends TransportInterface
{
    /**
     * @param string $name
     * @param callable $then
     */
    public function route(string $name, callable $then): void;
}
