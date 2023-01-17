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
     * @param string|TypeDto $type
     *
     * @return TypeInterface|null
     * @throws \ReflectionException
     */
    protected function ofType(MarshallerInterface $marshaller, TypeDto|string $type): ?TypeInterface
    {
        $of = $type instanceof TypeDto && $type->of !== null
            ? $this->ofType($marshaller, $type->of)
            : null;
        $typeClass = $type instanceof TypeDto ? $type->type : $type;

        \assert($typeClass !== null);

        if (Inheritance::implements($typeClass, TypeInterface::class)) {
            return $of === null
                ? new $typeClass($marshaller)
                : new $typeClass($marshaller, $of);
        }

        return new ObjectType($marshaller, $typeClass);
    }
}
