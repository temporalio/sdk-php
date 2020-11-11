<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Router;

use React\Promise\Deferred;
use Temporal\Client\Activity;
use Temporal\Client\Activity\ActivityContext;
use Temporal\Client\Activity\ActivityInfo;
use Temporal\Client\Internal\Declaration\Instantiator\ActivityInstantiator;
use Temporal\Client\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Client\Internal\Declaration\Prototype\Collection;

final class InvokeActivity extends Route
{
    /**
     * @var string
     */
    private const ERROR_NOT_FOUND = 'Activity with the specified name "%s" was not registered';

    /**
     * @var Collection<ActivityPrototype>
     */
    private Collection $activities;

    /**
     * @var ActivityInstantiator
     */
    private ActivityInstantiator $instantiator;

    /**
     * @param Collection<ActivityPrototype> $activities
     */
    public function __construct(Collection $activities)
    {
        $this->activities = $activities;
        $this->instantiator = new ActivityInstantiator();
    }

    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        $context = new ActivityContext($payload);

        $prototype = $this->findDeclarationOrFail($context->getInfo());
        $instance = $this->instantiator->instantiate($prototype);

        try {
            Activity::setCurrentContext($context);

            $handler = $instance->getHandler();
            $result = $handler($this->getArguments($context));

            $resolver->resolve($result);
        } finally {
            Activity::setCurrentContext(null);
        }
    }

    /**
     * @param ActivityContext $context
     * @return array
     */
    private function getArguments(ActivityContext $context): array
    {
        $arguments = [
            Activity\ActivityContextInterface::class => $context,
        ];

        return \array_merge($arguments, $context->getArguments());
    }

    /**
     * @param ActivityInfo $info
     * @return ActivityPrototype
     */
    private function findDeclarationOrFail(ActivityInfo $info): ActivityPrototype
    {
        $activity = $this->activities->find($info->type->name);

        if ($activity === null) {
            throw new \LogicException(\sprintf(self::ERROR_NOT_FOUND, $info->type->name));
        }

        return $activity;
    }
}
