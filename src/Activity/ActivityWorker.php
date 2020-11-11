<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

use React\Promise\PromiseInterface;
use Temporal\Client\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Client\Internal\Declaration\Prototype\Collection;
use Temporal\Client\Transport\DispatcherInterface;
use Temporal\Client\Transport\Protocol\Command\RequestInterface;
use Temporal\Client\Transport\Router;
use Temporal\Client\Transport\RouterInterface;
use Temporal\Client\Worker\Worker;

class ActivityWorker implements DispatcherInterface
{
    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    /**
     * @var Worker
     */
    private Worker $worker;

    /**
     * @param Collection<ActivityPrototype> $activities
     * @param Worker $worker
     */
    public function __construct(Collection $activities, Worker $worker)
    {
        $this->worker = $worker;

        $this->router = new Router();
        $this->router->add(new Router\InvokeActivity($activities));
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(RequestInterface $request, array $headers = []): PromiseInterface
    {
        return $this->router->dispatch($request, $headers);
    }
}
