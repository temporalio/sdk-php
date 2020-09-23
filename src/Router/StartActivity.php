<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Router;

use Temporal\Client\Declaration\ActivityInterface;
use Temporal\Client\Runtime\ActivityContext;
use Temporal\Client\Runtime\ActivityContextInterface;
use Temporal\Client\Transport\Request\RequestInterface;
use Temporal\Client\Transport\Request\StartActivity as StartActivityRequest;
use Temporal\Client\Transport\TransportInterface;
use Temporal\Client\WorkerInterface;

class StartActivity extends Route
{
    /**
     * @var string
     */
    private const ERROR_INVALID_ACTIVITY = 'Activity named "%s" not registered';

    /**
     * @param StartActivityRequest|RequestInterface $request
     * @return mixed
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function handle(RequestInterface $request)
    {
        if (! $activity = $this->worker->findActivity($request->get('name'))) {
            throw new \RuntimeException(\sprintf(self::ERROR_INVALID_ACTIVITY, $request->get('name')));
        }

        return $this->execute($activity, $request);
    }

    /**
     * TODO It shouldn't be in the router
     *
     * @param ActivityInterface $activity
     * @param StartActivityRequest $request
     * @return mixed
     * @throws \ReflectionException
     * @throws \Throwable
     */
    private function execute(ActivityInterface $activity, StartActivityRequest $request)
    {
        [$handler, $reflection] = [
            $activity->getHandler(),
            $activity->getReflectionHandler(),
        ];

        $context = new ActivityContext($request);

        $additional = [
            WorkerInterface::class          => $this->worker,
            TransportInterface::class       => $this->worker->getTransport(),
            RequestInterface::class         => $request,
            ActivityContext::class          => $context,
            ActivityContextInterface::class => $context,
        ];

        $result = $this->app->runScope($additional, function () use ($reflection, $request) {
            $arguments = [
                'arguments' => $payload = $request->get('arguments'),
            ];

            if (\is_array($payload)) {
                $arguments = \array_merge_recursive($arguments, $payload);
            }

            return $this->app->resolveArguments($reflection, $arguments);
        });

        return $handler(...$result);
    }

    /**
     * @return string
     */
    public function getRequest(): string
    {
        return StartActivityRequest::class;
    }
}
