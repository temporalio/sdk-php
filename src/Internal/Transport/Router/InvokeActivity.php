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
use Temporal\Client\Internal\ServiceContainer;
use Temporal\Client\Worker\Transport\RpcConnectionInterface;

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
        $this->instantiator = new ActivityInstantiator();
    }

    /**
     * {@inheritDoc}
     */
    public function handle(array $payload, array $headers, Deferred $resolver): void
    {
        $context = $this->services->marshaller->unmarshal($payload, new ActivityContext($this->rpc));

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
        $activity = $this->services->activities->find($info->type->name);

        if ($activity === null) {
            throw new \LogicException(\sprintf(self::ERROR_NOT_FOUND, $info->type->name));
        }

        return $activity;
    }
}
