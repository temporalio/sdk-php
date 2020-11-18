<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Marshaller\Meta;

use Temporal\Client\Internal\Marshaller\Type\ArrayType;
use Temporal\Client\Internal\Marshaller\Type\TypeInterface;

/**
 * @Annotation
 * @Target({ "PROPERTY", "METHOD" })
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MarshalArray extends Marshal
{
    /**
     * @var class-string<TypeInterface>|null
     */
    public ?string $type = ArrayType::class;
}
