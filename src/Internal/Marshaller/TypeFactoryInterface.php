<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller;

use Temporal\Internal\Marshaller\Type\TypeInterface;

interface TypeFactoryInterface
{
    /**
     * @param class-string<TypeInterface> $type
     * @param array $args
     *
     * @return TypeInterface|null
     */
    public function create(string $type, array $args): ?TypeInterface;

    /**
     * @param \ReflectionType|null $type
     *
     * @return class-string<TypeInterface>|null
     */
    public function detect(?\ReflectionType $type): ?string;
}
