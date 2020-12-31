<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use JetBrains\PhpStorm\Pure;
use React\Promise\PromiseInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Workflow\WorkflowContextInterface;

/**
 * @internal AsyncActivityProxy is an internal library class, please do not use it in your code.
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
     * @var WorkflowContextInterface
     */
    private WorkflowContextInterface $context;

    /**
     * @param class-string<Activity> $class
     * @param ActivityOptions $options
     * @param WorkflowContextInterface $context
     * @param RepositoryInterface<ActivityPrototype> $activities
     */
    public function __construct(
        string $class,
        ActivityOptions $options,
        WorkflowContextInterface $context,
        RepositoryInterface $activities
    )
    {
        $this->options = $options;
        $this->context = $context;

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

        return $this->context->executeActivity(
            $activity ? $activity->getId() : $method,
            $arguments,
            $this->options
        );
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
