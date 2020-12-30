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
     * @return iterable<T>
     */
    abstract public function fromClass(string $class): iterable;

    /**
     * @psalm-template Attribute of object
     *
     * @param \ReflectionClass $ctx
     * @param class-string<Attribute> $attribute
     * @return iterable<Attribute, \ReflectionMethod>
     */
    protected function annotatedMethods(\ReflectionClass $ctx, string $attribute): iterable
    {
        foreach ($ctx->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($this->reader->getFunctionMetadata($method, $attribute) as $meta) {
                yield $meta => $method;
            }
        }
    }
}
