<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Marshaller\Type;

interface TypeInterface
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public function parse($value);

    /**
     * @param mixed $value
     * @return mixed
     */
    public function serialize($value);
}
