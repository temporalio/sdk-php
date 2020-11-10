<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration;

use Temporal\Client\Internal\Declaration\ReaderInterface as DeclarationReaderInterface;
use Temporal\Client\Internal\Meta\Factory;
use Temporal\Client\Internal\Meta\ReaderInterface as MetadataReaderInterface;
use Temporal\Client\Internal\Meta\Selective\SelectiveReader;

abstract class Reader implements DeclarationReaderInterface
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
     * @psalm-template T of object
     * @psalm-param class-string<T> $attribute
     * @psalm-return iterable<T, \ReflectionMethod>
     *
     * @param \ReflectionClass $ctx
     * @param string $attribute
     * @return \ReflectionMethod[]
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
