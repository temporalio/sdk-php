<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Internal\Transport\Router\RouteInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

use function React\Promise\reject;

final class Router implements RouterInterface
{
    /**
     * @var string
     */
    private const ERROR_ROUTE_UNIQUENESS = 'Route "%s" has already been registered';

    /**
     * @var string
     */
    private const ERROR_ROUTE_NOT_FOUND = 'Method "%s" is not registered';

    /**
     * @var array|RouteInterface[]
     */
    private array $routes = [];

    /**
     * {@inheritDoc}
     */
    public function add(RouteInterface $route, bool $overwrite = false): void
    {
        if ($overwrite === false && isset($this->routes[$route->getName()])) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR_ROUTE_UNIQUENESS, $route->getName()));
        }

        $this->routes[$route->getName()] = $route;
    }

    /**
     * {@inheritDoc}
     */
    public function remove(ServerRequestInterface $route): void
    {
        unset($this->routes[$route->getName()]);
    }

    /**
     * @param ServerRequestInterface $request
     * @return RouteInterface|null
     */
    public function match(ServerRequestInterface $request): ?RouteInterface
    {
        return $this->routes[$request->getName()] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(ServerRequestInterface $request, array $headers = []): PromiseInterface
    {
        // assert($request instanceof ServerRequestInterface);
        $route = $this->match($request);

        if ($route === null) {
            $error = \sprintf(self::ERROR_ROUTE_NOT_FOUND, $request->getName());
            return reject(new \BadMethodCallException($error));
        }

        $deferred = new Deferred();
        try {
            $route->handle($request, $headers, $deferred);
        } catch (\Throwable $e) {
            $deferred->reject($e);
        }

        return $deferred->promise();
    }
}
