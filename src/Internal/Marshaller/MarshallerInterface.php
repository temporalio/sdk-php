<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Marshaller;

use Temporal\Client\Internal\Marshaller\Type\TypeInterface;

interface MarshallerInterface
{
    /**
     * @param object $from
     * @return array
     */
    public function marshal(object $from): array;

    /**
     * @param array $from
     * @param object $to
     * @return object
     */
    public function unmarshal(array $from, object $to): object;

    /**
     * @param class-string<TypeInterface> $type
     * @param array $args
     * @return TypeInterface|null
     */
    public function typeOf(string $type, array $args): ?TypeInterface;
}
