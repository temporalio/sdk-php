<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

interface DetectableTypeInterface
{
    /**
     * @param \ReflectionNamedType $type
     * @return bool
     */
    public static function match(\ReflectionNamedType $type): bool;
}
