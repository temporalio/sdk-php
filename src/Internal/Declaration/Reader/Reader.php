<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Reader;

use Spiral\Attributes\ReaderInterface;

/**
 * @psalm-template T of object
 */
abstract class Reader
{
    /**
     * @var ReaderInterface
     */
    protected ReaderInterface $reader;

    /**
     * @param ReaderInterface $reader
     */
    public function __construct(ReaderInterface $reader)
    {
        $this->reader = $reader;
    }

    /**
     * @param class-string $class
     * @return array<T>|T
     */
    abstract public function fromClass(string $class);

    /**
     * @param \ReflectionMethod $method
     * @return bool
     */
    protected function isValidMethod(\ReflectionMethod $method): bool
    {
        return !$method->isStatic() && $method->isPublic();
    }
}
