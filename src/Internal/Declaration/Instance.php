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
use Temporal\Exception\InstantiationException;
use Temporal\Internal\Declaration\Dispatcher\AutowiredPayloads;
use Temporal\Internal\Declaration\Prototype\Prototype;

/**
 * @psalm-import-type DispatchableHandler from InstanceInterface
 */
abstract class Instance implements InstanceInterface
{
    protected object $context;
    /**
     * @var \Closure(ValuesInterface): mixed
     */
    private \Closure $handler;

    /**
     * @param Prototype $prototype
     * @param object $context
     */
    public function __construct(Prototype $prototype, object $context)
    {
        $handler = $prototype->getHandler();

        if ($handler === null) {
            throw new InstantiationException(\sprintf(
                'Unable to instantiate "%s" without handler method',
                $prototype->getID(),
            ));
        }

        $this->context = $context;
        $this->handler = $this->createHandler($handler);
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
     * @param \ReflectionFunctionAbstract $func
     * @return \Closure(ValuesInterface): mixed
     *
     * @psalm-return DispatchableHandler
     */
    protected function createHandler(\ReflectionFunctionAbstract $func): \Closure
    {
        $valueMapper = new AutowiredPayloads($func);

        $context = $this->context;
        return static fn (ValuesInterface $values): mixed => $valueMapper->dispatchValues($context, $values);
    }
}
