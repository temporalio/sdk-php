<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Mapper;

use Spiral\Attributes\ReaderInterface;
use Temporal\Internal\Marshaller\TypeFactoryInterface;

class AttributeMapperFactory implements MapperFactoryInterface
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
     * {@inheritDoc}
     */
    public function create(\ReflectionClass $class, TypeFactoryInterface $types): MapperInterface
    {
        return new AttributeMapper($class, $types, $this->reader);
    }
}
