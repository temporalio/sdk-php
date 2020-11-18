<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Marshaller;

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
}
