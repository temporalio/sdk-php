<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Router;

use Temporal\Client\Protocol\DispatcherInterface;

interface RouteInterface
{
    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param array $payload
     * @param array $headers
     * @return mixed
     */
    public function handle(array $payload, array $headers);
}
