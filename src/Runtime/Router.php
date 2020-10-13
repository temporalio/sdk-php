<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime;

use React\Promise\Deferred;
use Temporal\Client\Protocol\ClientInterface;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Runtime\Route\RouteInterface;

final class Router implements RouterInterface
{
    /**
     * @var string
     */
    private const ERROR_ROUTE_UNIQUENESS = 'Route "%s" has already been registered';

    /**
     * @var string
     */
    private const ERROR_ROUTE_NOT_FOUND = 'Method "%s" is not registered by the server implementation';

    /**
     * @var ClientInterface
     */
    private ClientInterface $client;

    /**
     * @var array|RouteInterface[]
     */
    private array $routes = [];

    /**
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * {@inheritDoc}
     */
    public function add(RouteInterface $route): void
    {
        if (isset($this->routes[$route->getName()])) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_ROUTE_UNIQUENESS, $route->getName()));
        }

        $this->routes[$route->getName()] = $route;
    }

    /**
     * {@inheritDoc}
     */
    public function remove(RouteInterface $route): void
    {
        unset($this->routes[$route->getName()]);
    }

    /**
     * {@inheritDoc}
     */
    public function emit(RequestInterface $request, Deferred $resolver): void
    {
        $method = $request->getName();
        $route = $this->routes[$method] ?? null;

        if ($route === null) {
            $error = \sprintf(self::ERROR_ROUTE_NOT_FOUND, $method);
            $resolver->reject(new \BadMethodCallException($error));

            return;
        }

        $route->handle($request->getParams(), $resolver);
    }
}
