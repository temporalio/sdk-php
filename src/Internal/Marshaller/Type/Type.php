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

abstract class Type implements TypeInterface
{
    /**
     * @var MarshallerInterface
     */
    protected MarshallerInterface $marshaller;

    /**
     * @param MarshallerInterface $marshaller
     */
    public function __construct(MarshallerInterface $marshaller)
    {
        $this->marshaller = $marshaller;
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
        $of = $type instanceof MarshallingRule && $type->of !== null
            ? $type->of
            : null;
        $typeClass = $type instanceof MarshallingRule ? $type->type : $type;

        \assert($typeClass !== null);

        if (Inheritance::implements($typeClass, TypeInterface::class)) {
            return $of === null
                ? new $typeClass($marshaller)
                : new $typeClass($marshaller, $of);
        }

        return new ObjectType($marshaller, $typeClass);
    }
}
