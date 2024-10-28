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
    protected string $name;

    protected ?\ReflectionMethod $handler;

    private \ReflectionClass $class;

    public function __construct(string $name, ?\ReflectionMethod $handler, \ReflectionClass $class)
    {
        $this->handler = $handler;
        $this->name = $name;
        $this->class = $class;
    }

    /**
     * @template T of PrototypeInterface
     *
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

    public function getID(): string
    {
        return $this->name;
    }

    public function getClass(): \ReflectionClass
    {
        return $this->class;
    }

    public function getHandler(): ?\ReflectionMethod
    {
        return $this->handler;
    }

    private static function matchClass(PrototypeInterface $prototype, string $class): bool
    {
        $reflection = $prototype->getClass();

        return $reflection && $reflection->getName() === \trim($class, '\\');
    }

    private static function matchMethod(PrototypeInterface $prototype, string $method): bool
    {
        $handler = $prototype->getHandler();

        return $handler?->getName() === $method;
    }
}
