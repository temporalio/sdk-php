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
     * @var \ReflectionMethod|null
     */
    protected ?\ReflectionMethod $handler;

    /**
     * @var \ReflectionClass
     */
    private \ReflectionClass $class;

    /**
     * @param string $name
     * @param \ReflectionMethod|null $handler
     * @param \ReflectionClass $class
     */
    public function __construct(string $name, ?\ReflectionMethod $handler, \ReflectionClass $class)
    {
        $this->handler = $handler;
        $this->name = $name;
        $this->class = $class;
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
     * @return string
     */
    public function getID(): string
    {
        return $this->name;
    }

    /**
     * @return \ReflectionClass
     */
    public function getClass(): \ReflectionClass
    {
        return $this->class;
    }

    /**
     * @return \ReflectionMethod|null
     */
    public function getHandler(): ?\ReflectionMethod
    {
        return $this->handler;
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

        return $handler?->getName() === $method;
    }
}
