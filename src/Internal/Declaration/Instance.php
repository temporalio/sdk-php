<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Declaration\Dispatcher\AutowiredPayloads;
use Temporal\Internal\Declaration\Prototype\Prototype;

/**
 * @psalm-import-type DispatchableHandler from InstanceInterface
 */
abstract class Instance implements InstanceInterface
{
    protected Prototype $prototype;
    protected ?object $context;
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
     * @return object|null
     */
    public function getContext(): ?object
    {
        return $this->context;
    }

    /**
     * {@inheritDoc}
     */
    public function getHandler(): callable
    {
        return $this->handler;
    }

    /**
     * @psalm-return DispatchableHandler
     *
     * @param \ReflectionFunctionAbstract $func
     * @return \Closure
     */
    protected function createHandler(\ReflectionFunctionAbstract $func): \Closure
    {
        $valueMapper = new AutowiredPayloads($func);

        return fn (ValuesInterface $values) => $valueMapper->dispatchValues($this->context, $values);
    }

    /**
     * @param callable $handler
     * @return \Closure
     * @throws \ReflectionException
     */
    protected function createCallableHandler(callable $handler): \Closure
    {
        return $this->createHandler(new \ReflectionFunction($handler));
    }
}
