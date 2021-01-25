<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller;

interface MarshallerInterface
{
    /**
     * @template T of object
     * @param T $from
     * @return array
     */
    public function marshal(object $from): array;

    /**
     * @template T of object
     * @param array $from
     * @param T $to
     * @return T
     */
    public function unmarshal(array $from, object $to): object;
}
