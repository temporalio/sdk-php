<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\Client\Activity;
use Temporal\Client\Activity\ActivityContext;
use Temporal\Client\Activity\ActivityInfo;
use Temporal\Client\Exception\DoNotCompleteOnResultException;
use Temporal\Client\Internal\Declaration\Instantiator\ActivityInstantiator;
use Temporal\Client\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Client\Internal\Declaration\Prototype\Collection;
use Temporal\Client\Internal\Marshaller\MarshallerInterface;

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
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @param MarshallerInterface $marshaller
     * @param Collection<ActivityPrototype> $activities
     */
    public function __construct(MarshallerInterface $marshaller, Collection $activities)
    {
        $this->marshaller = $marshaller;
        $this->activities = $activities;
        $this->instantiator = new ActivityInstantiator();
    }

    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        $context = $this->marshaller->unmarshal($payload, new ActivityContext());

        $prototype = $this->findDeclarationOrFail($context->getInfo());
        $instance = $this->instantiator->instantiate($prototype);

        try {
            Activity::setCurrentContext($context);

            $handler = $instance->getHandler();
            $result = $handler($context->getArguments());

            if ($context->isDoNotCompleteOnReturn()) {
                $resolver->reject(DoNotCompleteOnResultException::create());
            } else {
                $resolver->resolve($result);
            }
        } finally {
            Activity::setCurrentContext(null);
        }
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
