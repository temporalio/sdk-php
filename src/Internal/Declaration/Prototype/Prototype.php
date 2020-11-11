<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration\Prototype;

abstract class Prototype implements PrototypeInterface
{
    /**
     * @var string
     */
    protected string $name;

    /**
     * @var \ReflectionFunctionAbstract
     */
    protected \ReflectionFunctionAbstract $handler;

    /**
     * @var \ReflectionClass|null
     */
    private ?\ReflectionClass $class;

    /**
     * @param string $name
     * @param \ReflectionFunctionAbstract $handler
     * @param \ReflectionClass|null $class
     */
    public function __construct(string $name, \ReflectionFunctionAbstract $handler, ?\ReflectionClass $class)
    {
        $this->handler = $handler;
        $this->name = $name;
        $this->class = $class;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return \ReflectionClass|null
     */
    public function getClass(): ?\ReflectionClass
    {
        return $this->class;
    }

    /**
     * @return \ReflectionFunctionAbstract
     */
    public function getHandler(): \ReflectionFunctionAbstract
    {
        return $this->handler;
    }
}
