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

/**
 * @template-covariant TMarshalType of mixed
 */
interface TypeInterface
{
    /**
     * @param MarshallerInterface<array> $marshaller
     */
    public function __construct(MarshallerInterface $marshaller);

    /**
     * @param mixed $value
     * @param mixed $current
     * @return mixed
     */
    public function parse($value, $current);

    /**
     * @param mixed $value
     * @return TMarshalType
     */
    public function serialize($value);
}
