<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Reader;

use JetBrains\PhpStorm\Pure;
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
     * @psalm-suppress ImpureMethodCall
     *
     * @param \ReflectionMethod $method
     * @return bool
     */
    #[Pure]
    protected function isValidMethod(\ReflectionMethod $method): bool
    {
        return ! $method->isStatic() && $method->isPublic();
    }

    /**
     * @param class-string $class
     * @return array<T>|T
     */
    abstract public function fromClass(string $class);
}
