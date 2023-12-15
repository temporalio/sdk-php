<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\MarshallingRule;
use Temporal\Internal\Support\Inheritance;

/**
 * @template-covariant TSerializeType of mixed
 * @implements TypeInterface<TSerializeType>
 */
abstract class Type implements TypeInterface
{
    /**
     * @param MarshallerInterface<array> $marshaller
     */
    public function __construct(
        protected MarshallerInterface $marshaller
    ) {
    }

    /**
     * @param MarshallerInterface $marshaller
     * @param string|MarshallingRule $type
     *
     * @return TypeInterface|null
     * @throws \ReflectionException
     */
    protected function ofType(MarshallerInterface $marshaller, MarshallingRule|string $type): ?TypeInterface
    {
        $args = $type instanceof MarshallingRule ? $type->getConstructorArgs() : [];
        $typeClass = $type instanceof MarshallingRule ? $type->type : $type;

        if ($typeClass === null) {
            return null;
        }

        if (Inheritance::implements($typeClass, TypeInterface::class)) {
            return new $typeClass($marshaller, ...$args);
        }

        return new ObjectType($marshaller, $typeClass);
    }
}
