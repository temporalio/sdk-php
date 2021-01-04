<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Router;

use React\Promise\Deferred;
use Temporal\Activity;
use Temporal\Activity\ActivityContext;
use Temporal\Activity\ActivityInfo;
use Temporal\Exception\DoNotCompleteOnResultException;
use Temporal\Internal\Declaration\Instantiator\ActivityInstantiator;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\ServiceContainer;
use Temporal\Worker\Transport\RpcConnectionInterface;

final class InvokeActivity extends Route
{
    /**
     * @var string
     */
    private const ERROR_NOT_FOUND = 'Activity with the specified name "%s" was not registered';

    /**
     * @var ActivityInstantiator
     */
    private ActivityInstantiator $instantiator;

    /**
     * @var ServiceContainer
     */
    private ServiceContainer $services;

    /**
     * @var RpcConnectionInterface
     */
    private RpcConnectionInterface $rpc;

    /**
     * @param ServiceContainer $services
     * @param RpcConnectionInterface $rpc
     */
    public function __construct(ServiceContainer $services, RpcConnectionInterface $rpc)
    {
        $this->rpc = $rpc;
        $this->services = $services;
        $this->instantiator = new ActivityInstantiator($services->dataConverter);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        $context = new ActivityContext($this->rpc, $this->services->dataConverter);
        $context = $this->services->marshaller->unmarshal($payload, $context);

        $prototype = $this->findDeclarationOrFail($context->getInfo());

        // todo: get from container
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
        $activity = $this->services->activities->find($info->type->name);

        if ($activity === null) {
            throw new \LogicException(\sprintf(self::ERROR_NOT_FOUND, $info->type->name));
        }

        return $activity;
    }
}
