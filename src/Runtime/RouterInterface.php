<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime;

use Temporal\Client\Runtime\Route\RouteInterface;

interface RouterInterface extends DispatcherInterface
{
    /**
     * @param RouteInterface $route
     */
    public function add(RouteInterface $route): void;

    /**
     * @param RouteInterface $route
     */
    public function remove(RouteInterface $route): void;
}
