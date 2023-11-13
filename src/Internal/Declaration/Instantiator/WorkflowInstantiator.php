<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Instantiator;

use Temporal\Exception\InstantiationException;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;
use Temporal\Internal\Declaration\Prototype\PrototypeInterface;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Declaration\WorkflowInstance;

/**
 * @template-implements InstantiatorInterface<WorkflowPrototype, WorkflowInstance>
 */
final class WorkflowInstantiator extends Instantiator
{
    public function __construct(
        private \Temporal\Interceptor\PipelineProvider $interceptorProvider,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function instantiate(PrototypeInterface $prototype): WorkflowInstance
    {
        assert($prototype instanceof WorkflowPrototype, 'Precondition failed');

        return new WorkflowInstance(
            $prototype,
            $this->getInstance($prototype),
            $this->interceptorProvider->getPipeline(WorkflowInboundCallsInterceptor::class),
        );
    }

    /**
     * @param PrototypeInterface $prototype
     * @return object|null
     * @throws \ReflectionException
     */
    protected function getInstance(PrototypeInterface $prototype): ?object
    {
        $handler = $prototype->getHandler();

        if ($handler === null) {
            throw new InstantiationException(\sprintf(
                'Unable to instantiate workflow "%s" without handler method',
                $prototype->getID(),
            ));
        }

        $class = $handler->getDeclaringClass();

        if ($class !== null) {
            return $class->newInstanceWithoutConstructor();
        }

        return null;
    }
}
