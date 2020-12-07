<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Client\Internal\Repository\RepositoryInterface;
use Temporal\Client\Workflow\Context\RequestsInterface;

/**
 * @internal ActivityProxy is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client
 *
 * @psalm-template Activity of object
 */
class ActivityProxy
{
    /**
     * @var ActivityPrototype[]
     */
    private array $activities;

    /**
     * @var ActivityOptions
     */
    private ActivityOptions $options;

    /**
     * @var RequestsInterface
     */
    private RequestsInterface $requests;

    /**
     * @param class-string<Activity> $class
     * @param ActivityOptions $options
     * @param RequestsInterface $requests
     * @param RepositoryInterface<ActivityPrototype> $activities
     */
    public function __construct(
        string $class,
        ActivityOptions $options,
        RequestsInterface $requests,
        RepositoryInterface $activities
    ) {
        $this->options = $options;
        $this->requests = $requests;

        $this->activities = [
            ...$this->filterActivities($activities, $class)
        ];
    }

    /**
     * @param ActivityPrototype[] $activities
     * @param string $class
     * @return \Traversable
     */
    private function filterActivities(iterable $activities, string $class): \Traversable
    {
        foreach ($activities as $activity) {
            if ($this->matchClass($activity, $class)) {
                yield $activity;
            }
        }
    }

    /**
     * @param ActivityPrototype $prototype
     * @param string $needle
     * @return bool
     */
    private function matchClass(ActivityPrototype $prototype, string $needle): bool
    {
        $reflection = $prototype->getClass();

        return $reflection && $reflection->getName() === \trim($needle, '\\');
    }

    /**
     * @param string $method
     * @param array $arguments
     * @return PromiseInterface
     */
    public function __call(string $method, array $arguments = []): PromiseInterface
    {
        return $this->call($method, $arguments);
    }

    /**
     * @param string $method
     * @param array $arguments
     * @return PromiseInterface
     */
    public function call(string $method, array $arguments = []): PromiseInterface
    {
        $activity = $this->findActivityPrototype($method);

        $method = $activity ? $activity->getId() : $method;

        return $this->requests->executeActivity($method, $arguments, $this->options);
    }

    /**
     * @param string $method
     * @return ActivityPrototype|null
     */
    private function findActivityPrototype(string $method): ?ActivityPrototype
    {
        foreach ($this->activities as $activity) {
            if ($this->matchMethod($activity, $method)) {
                return $activity;
            }
        }

        return null;
    }

    /**
     * @param ActivityPrototype $prototype
     * @param string $needle
     * @return bool
     */
    private function matchMethod(ActivityPrototype $prototype, string $needle): bool
    {
        $handler = $prototype->getHandler();

        return $handler->getName() === $needle;
    }
}
