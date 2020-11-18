<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration;

use Temporal\Client\Internal\Declaration\Dispatcher\Autowired;
use Temporal\Client\Internal\Declaration\Prototype\Prototype;

/**
 * @psalm-import-type DispatchableHandler from InstanceInterface
 */
abstract class Instance implements InstanceInterface
{
    /**
     * @var Prototype
     */
    protected Prototype $prototype;

    /**
     * @var object|null
     */
    protected ?object $context;

    /**
     * @var \Closure
     */
    private \Closure $handler;

    /**
     * @param Prototype $prototype
     * @param object|null $context
     */
    public function __construct(Prototype $prototype, ?object $context)
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
            return $dispatcher->dispatch($this->context, $arguments);
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getHandler(): callable
    {
        return $this->handler;
    }
}
