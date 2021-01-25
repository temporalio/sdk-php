<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport;

use Temporal\Internal\Transport\Router\RouteInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\DispatcherInterface;

interface RouterInterface extends DispatcherInterface
{
    /**
     * @param RouteInterface $route
     * @param bool $overwrite
     */
    public function add(RouteInterface $route, bool $overwrite = false): void;

    /**
     * @param RouteInterface $route
     */
    public function remove(RouteInterface $route): void;

    /**
     * @param RequestInterface $request
     * @return RouteInterface|null
     */
    public function match(RequestInterface $request): ?RouteInterface;
}
