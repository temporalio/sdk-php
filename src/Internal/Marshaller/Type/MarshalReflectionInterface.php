<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use Temporal\Internal\Marshaller\Meta\Marshal;

/**
 * The type can detect the property type information from its reflection.
 */
interface MarshalReflectionInterface extends TypeInterface
{
    /**
     * @param \ReflectionProperty $property
     *
     * @return Marshal|null
     */
    public static function reflectMarshal(\ReflectionProperty $property): ?TypeDto;
}
