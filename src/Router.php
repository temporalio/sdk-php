<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use React\Promise\PromiseInterface;
use Spiral\Core\Container;
use Spiral\Goridge\Message\ReceivedMessageInterface;
use Temporal\Client\Router\RouteInterface;
use Temporal\Client\Router\StartActivity;
use Temporal\Client\Router\StartWorkflow;
use Temporal\Client\Transport\Request\RequestInterface;
use Temporal\Client\Transport\RoutableTransportInterface;
use Temporal\Client\Transport\TransportInterface;

class Router implements TransportInterface
{
    /**
     * @var RoutableTransportInterface
     */
    private RoutableTransportInterface $router;

    /**
     * @var WorkerInterface
     */
    private WorkerInterface $worker;

    /**
     * @var Container
     */
    private Container $app;

    /**
     * @param Container $app
     * @param RoutableTransportInterface $router
     * @param WorkerInterface $worker
     */
    public function __construct(Container $app, RoutableTransportInterface $router, WorkerInterface $worker)
    {
        $this->app = $app;
        $this->router = $router;
        $this->worker = $worker;

        $this->boot();
    }

    /**
     * @param RouteInterface $route
     */
    public function add(RouteInterface $route): void
    {
        $this->router->route($route->getName(), static function (array $payload) use ($route) {
            $response = $route->getRequest();

            return $route->handle(new $response($payload));
        });
    }

    /**
     * @return void
     */
    private function boot(): void
    {
        $this->add(new StartWorkflow($this->app, $this->worker));
        $this->add(new StartActivity($this->app, $this->worker));
    }

    /**
     * {@inheritDoc}
     */
    public function send(RequestInterface $request): PromiseInterface
    {
        return $this->router->send($request);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ReceivedMessageInterface $message): void
    {
        $this->router->handle($message);
    }
}
