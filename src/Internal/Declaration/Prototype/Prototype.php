<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Prototype;

use Temporal\Internal\Repository\RepositoryInterface;

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
     * @var bool
     */
    private bool $interfaced;

    /**
     * @param string $name
     * @param \ReflectionFunctionAbstract $handler
     * @param \ReflectionClass|null $class
     * @param bool $interfaced
     */
    public function __construct(
        string $name,
        \ReflectionFunctionAbstract $handler,
        ?\ReflectionClass $class,
        bool $interfaced = false
    ) {
        $this->handler = $handler;
        $this->name = $name;
        $this->class = $class;
        $this->interfaced = $interfaced;
    }

    /**
     * {@inheritDoc}
     */
    public function isInterfaced(): bool
    {
        return $this->interfaced;
    }

    /**
     * @template T of PrototypeInterface
     *
     * @param string $class
     * @param string $method
     * @param RepositoryInterface<T> $repository
     * @return T|null
     */
    public static function find(string $class, string $method, RepositoryInterface $repository): ?PrototypeInterface
    {
        /** @var PrototypeInterface $prototype */
        foreach ($repository as $prototype) {
            if (self::matchClass($prototype, $class) && self::matchMethod($prototype, $method)) {
                return $prototype;
            }
        }

        return null;
    }

    /**
     * @param PrototypeInterface $prototype
     * @param string $class
     * @return bool
     */
    private static function matchClass(PrototypeInterface $prototype, string $class): bool
    {
        $reflection = $prototype->getClass();

        return $reflection && $reflection->getName() === \trim($class, '\\');
    }

    /**
     * @param PrototypeInterface $prototype
     * @param string $method
     * @return bool
     */
    private static function matchMethod(PrototypeInterface $prototype, string $method): bool
    {
        $handler = $prototype->getHandler();

        return $handler->getName() === $method;
    }

    /**
     * @return string
     */
    public function getID(): string
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
