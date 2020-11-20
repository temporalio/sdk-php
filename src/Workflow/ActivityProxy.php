<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Client\Internal\Declaration\Prototype\Collection;

/**
 * @psalm-template Activity of object
 */
class ActivityProxy
{
    /**
     * @var WorkflowContextInterface
     */
    private WorkflowContextInterface $protocol;

    /**
     * @var ActivityPrototype[]
     */
    private iterable $activities;

    /**
     * @var ActivityOptions
     */
    private ActivityOptions $options;

    /**
     * @param class-string<Activity> $class
     * @param ActivityOptions $options
     * @param WorkflowContextInterface $protocol
     * @param Collection<ActivityPrototype> $activities
     */
    public function __construct(
        string $class,
        ActivityOptions $options,
        WorkflowContextInterface $protocol,
        Collection $activities
    ) {
        $this->protocol = $protocol;
        $this->options = $options;

        $this->activities = [...$this->filterActivities($activities, $class)];
    }

    /**
     * @param ActivityPrototype[] $activities
     * @param string $class
     * @return iterable
     */
    private function filterActivities(iterable $activities, string $class): iterable
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

        $method = $activity ? $activity->getName() : $method;

        return $this->protocol->executeActivity($method, $arguments, $this->options);
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
