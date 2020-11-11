<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration\Reader;

use Temporal\Client\Internal\Meta\Factory;
use Temporal\Client\Internal\Meta\ReaderInterface as MetadataReaderInterface;

/**
 * @psalm-template T of object
 */
abstract class Reader
{
    /**
     * @var MetadataReaderInterface
     */
    protected MetadataReaderInterface $reader;

    /**
     * @param MetadataReaderInterface|null $reader
     */
    public function __construct(MetadataReaderInterface $reader = null)
    {
        $this->reader = $reader ?? (new Factory())->create();
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
            foreach ($this->reader->getMethodMetadata($method, $attribute) as $meta) {
                yield $meta => $method;
            }
        }
    }
}
