<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Dispatcher;

use Temporal\DataConverter\DataConverterInterface;

/**
 * @psalm-type FunctionExecutor = \Closure(object|null, array): mixed
 */
class AutowiredPayloads extends Dispatcher
{
    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $dataConverter;

    /**
     * @param \ReflectionFunctionAbstract $fun
     * @param DataConverterInterface $dataConverter
     */
    public function __construct(\ReflectionFunctionAbstract $fun, DataConverterInterface $dataConverter)
    {
        $this->dataConverter = $dataConverter;

        parent::__construct($fun);
    }

    /**
     * @param array $arguments
     * @return array
     */
    public function resolve(array $arguments): array
    {
        return $this->dataConverter->fromPayloads($arguments, $this->getArgumentTypes());
    }

    /**
     * @param object|null $ctx
     * @param array $arguments
     * @return mixed
     */
    public function dispatch(?object $ctx, array $arguments)
    {
        return parent::dispatch($ctx, $this->resolve($arguments));
    }
}
