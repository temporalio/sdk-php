<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Instance;

use Temporal\Client\Internal\Instance\Dispatcher\Autowired;
use Temporal\Client\Internal\Prototype\PrototypeInterface;

/**
 * @psalm-import-type DispatchableHandler from InstanceInterface
 */
abstract class Instance implements InstanceInterface
{
    /**
     * @var PrototypeInterface
     */
    protected PrototypeInterface $prototype;

    /**
     * @var object
     */
    protected object $context;

    /**
     * @var \Closure
     */
    private \Closure $handler;

    /**
     * @param PrototypeInterface $prototype
     * @param object $context
     */
    public function __construct(PrototypeInterface $prototype, object $context)
    {
        $this->prototype = $prototype;
        $this->context = $context;
        $this->handler = $this->createHandler($prototype->getHandler());
    }

    /**
     * @psalm-return DispatchableHandler
     *
     * @param \ReflectionFunctionAbstract $fun
     * @return \Closure
     */
    protected function createHandler(\ReflectionFunctionAbstract $fun): \Closure
    {
        $dispatcher = new Autowired($fun);

        return function (array $arguments) use ($dispatcher) {
            return $dispatcher->dispatch($this->getContext(), $arguments);
        };
    }

    /**
     * @return callable
     */
    public function getHandler(): callable
    {
        return $this->handler;
    }

    /**
     * {@inheritDoc}
     */
    public function getContext(): object
    {
        return $this->context;
    }

    /**
     * {@inheritDoc}
     */
    public function getMethod(): object
    {
        return $this->prototype->getMethod();
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata(): object
    {
        return $this->prototype->getMetadata();
    }
}
