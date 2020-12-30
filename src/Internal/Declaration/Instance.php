<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration;

use Temporal\Client\DataConverter\DataConverterInterface;
use Temporal\Client\Internal\Declaration\Dispatcher\AutowiredPayloads;
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
     * @var DataConverterInterface
     */
    private DataConverterInterface $dataConverter;

    /**
     * @param Prototype $prototype
     * @param DataConverterInterface $dataConverter
     * @param object|null $context
     */
    public function __construct(Prototype $prototype, DataConverterInterface $dataConverter, ?object $context)
    {
        $this->prototype = $prototype;
        $this->context = $context;
        $this->dataConverter = $dataConverter;
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
     * @return DataConverterInterface
     */
    public function getDataConverter(): DataConverterInterface
    {
        return $this->dataConverter;
    }

    /**
     * @psalm-return DispatchableHandler
     *
     * @param \ReflectionFunctionAbstract $fun
     * @return \Closure
     */
    protected function createHandler(\ReflectionFunctionAbstract $fun): \Closure
    {
        $dispatcher = new AutowiredPayloads($fun, $this->dataConverter);

        return function (array $arguments = []) use ($dispatcher) {
            return $dispatcher->dispatch($this->context, $arguments);
        };
    }

    /**
     * @param callable $callable
     * @return \Closure
     */
    protected function createCallableHandler(callable $handler): \Closure
    {
        return $this->createHandler(new \ReflectionFunction($handler));
    }

    /**
     * {@inheritDoc}
     */
    public function getHandler(): callable
    {
        return $this->handler;
    }
}
