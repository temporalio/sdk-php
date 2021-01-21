<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Graph;

use Spiral\Attributes\ReaderInterface;

final class Factory
{
    /**
     * @var ReaderInterface
     */
    private ReaderInterface $reader;

    /**
     * @param ReaderInterface $reader
     */
    public function __construct(ReaderInterface $reader)
    {
        $this->reader = $reader;
    }

    /**
     * @param \ReflectionClass $class
     * @return ClassNode
     */
    public function fromReflectionClass(\ReflectionClass $class): ClassNode
    {
        return new ClassNode($class, $this->reader);
    }

    /**
     * @param string $name
     * @return ClassNode
     * @throws \ReflectionException
     */
    public function fromClass(string $name): ClassNode
    {
        return $this->fromReflectionClass(new \ReflectionClass($name));
    }
}
