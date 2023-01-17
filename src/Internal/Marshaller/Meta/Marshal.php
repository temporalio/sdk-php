<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Meta;

use Spiral\Attributes\NamedArgumentConstructorAttribute;
use Temporal\Internal\Marshaller\Type\TypeDto;

/**
 * @Annotation
 * @Target({ "PROPERTY" })
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Marshal extends TypeDto implements NamedArgumentConstructorAttribute
{
    /**
     * @return TypeDto
     */
    public function toTypeDto(): TypeDto
    {
        return $this;
    }
}
