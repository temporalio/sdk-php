<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller;

use Temporal\Exception\MarshallerException;

/**
 * @template-covariant TTarget of mixed
 */
interface MarshallerInterface
{
    /**
     * @return TTarget
     *
     * @throws MarshallerException
     */
    public function marshal(object $from): mixed;

    /**
     * @template T of object
     * @param array $from
     * @param T $to
     * @return T
     *
     * @throws MarshallerException
     */
    public function unmarshal(array $from, object $to): object;
}
